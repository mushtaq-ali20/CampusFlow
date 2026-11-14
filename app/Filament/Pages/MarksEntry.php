<?php

namespace App\Filament\Pages;

use App\Enums\AdmissionStatus;
use App\Enums\ExamType;
use App\Models\Admission;
use App\Models\Section;
use App\Models\SubjectResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Marks of a subject in an exam (quarterly, half yearly or annual) for all the students of a section. A student
 * who passes all the subjects in the annual exam is promoted to the next class. Teachers enter the marks of
 * the subjects they teach.
 */
class MarksEntry extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('evaluation.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Marks entry');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Marks entry');
    }

    /**
     * Staff with the evaluation permission enter the marks of any subject; a teacher only those they teach.
     */
    public function canEnterMarks(): bool
    {
        return (auth()->user()?->can('evaluation.edit') ?? false) || (auth()->user()?->isTeacher() ?? false);
    }

    /**
     * @return Builder<Section>
     */
    protected function sections(): Builder
    {
        $user = auth()->user();

        return Section::query()
            ->visibleTo($user)
            ->when(! $user->can('evaluation.view'), fn (Builder $query) => $query->whereHas('teachingAssignments', fn (Builder $assignments) => $assignments->where('teacher_id', $user->id)));
    }

    /**
     * @return array<int, string>
     */
    protected function subjectOptions(): array
    {
        $user = auth()->user();
        $section = $this->sections()->with('schoolClass.subjects')->find($this->data['section_id'] ?? null);

        if ($section === null) {
            return [];
        }

        $subjects = $section->schoolClass->subjects;

        if (! $user->can('evaluation.view')) {
            $taught = $section->teachingAssignments()->where('teacher_id', $user->id)->pluck('subject_id');
            $subjects = $subjects->whereIn('id', $taught);
        }

        return $subjects->pluck('name', 'id')->all();
    }

    public function mount(): void
    {
        $this->form->fill(['exam' => ExamType::Annual->value, 'rows' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Select::make('exam')
                        ->label(__('Exam'))
                        ->options(ExamType::class)
                        ->helperText(__('Students are promoted by passing the Annual exam.'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadRows()),
                    Select::make('section_id')
                        ->label(__('Section'))
                        ->options(fn (): array => $this->sections()
                            ->with(['schoolClass', 'campus'])
                            ->get()
                            ->sortBy(fn (Section $section): string => $section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
                            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])
                            ->all())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->data['subject_id'] = null;
                            $this->loadRows();
                        }),
                    Select::make('subject_id')
                        ->label(__('Subject'))
                        ->options(fn (): array => $this->subjectOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->loadRows()),
                ])->extraAttributes(['class' => 'cf-filter-bar'])->columnSpanFull(),
                View::make('filament.components.pick-first')
                    ->viewData(['heading' => __('Pick a section and a subject'), 'description' => __('The class list opens here with a marks box for each student.')])
                    ->columnSpanFull()
                    ->visible(fn (): bool => blank($this->data['section_id'] ?? null) || blank($this->data['subject_id'] ?? null)),
                Repeater::make('rows')
                    ->label(fn (): string => __('Students').$this->totalMarksLabel())
                    ->schema([
                        Hidden::make('admission_id'),
                        TextInput::make('name')
                            ->label(__('Student'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('obtained_marks')
                            ->label(__('Obtained marks'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (): bool => filled($this->data['section_id'] ?? null) && filled($this->data['subject_id'] ?? null)),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save marks'))
                                ->submit('save')
                                ->visible(fn (): bool => $this->canEnterMarks() && filled($this->data['subject_id'] ?? null)),
                        ]),
                    ]),
            ]);
    }

    protected function totalMarksLabel(): string
    {
        $marks = $this->marksOfSubject();

        return $marks ? ' ('.__('total :total, passing :passing', ['total' => $marks->total_marks, 'passing' => $marks->passing_marks]).')' : '';
    }

    /**
     * The total and passing marks of the chosen subject in the class of the chosen section.
     */
    protected function marksOfSubject(): ?object
    {
        $section = Section::query()->find($this->data['section_id'] ?? null);
        $subjectId = $this->data['subject_id'] ?? null;

        if ($section === null || blank($subjectId)) {
            return null;
        }

        return $section->schoolClass->subjects()->whereKey($subjectId)->first()?->pivot;
    }

    public function loadRows(): void
    {
        $sectionId = $this->data['section_id'] ?? null;
        $subjectId = $this->data['subject_id'] ?? null;

        if (blank($sectionId) || blank($subjectId) || ! array_key_exists((int) $subjectId, $this->subjectOptions())) {
            $this->data['rows'] = [];

            return;
        }

        $marks = SubjectResult::query()
            ->where('subject_id', $subjectId)
            ->where('exam', $this->data['exam'] ?? ExamType::Annual->value)
            ->pluck('obtained_marks', 'admission_id');

        $this->data['rows'] = Admission::query()
            ->with('student')
            ->where('section_id', $sectionId)
            ->where('status', AdmissionStatus::Active)
            ->get()
            ->sortBy(fn (Admission $admission): string => $admission->student->name)
            ->map(fn (Admission $admission): array => [
                'admission_id' => $admission->id,
                'name' => $admission->student->name,
                'obtained_marks' => $marks[$admission->id] ?? null,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        abort_unless($this->canEnterMarks(), 403);

        $state = $this->form->getState();
        abort_unless(array_key_exists((int) $state['subject_id'], $this->subjectOptions()), 403);

        $section = $this->sections()->findOrFail($state['section_id']);
        $total = $this->marksOfSubject()?->total_marks ?? 100;
        $admissions = Admission::query()->where('section_id', $section->id)->where('status', AdmissionStatus::Active)->pluck('id');

        foreach ($state['rows'] as $index => $row) {
            if (blank($row['obtained_marks'] ?? null)) {
                continue;
            }

            if ($row['obtained_marks'] > $total) {
                throw ValidationException::withMessages(["data.rows.{$index}.obtained_marks" => __('The marks cannot be greater than :total.', ['total' => $total])]);
            }
        }

        foreach ($state['rows'] as $row) {
            if (blank($row['obtained_marks'] ?? null) || ! $admissions->contains((int) $row['admission_id'])) {
                continue;
            }

            SubjectResult::query()->updateOrCreate(
                ['admission_id' => $row['admission_id'], 'subject_id' => $state['subject_id'], 'exam' => $state['exam']],
                ['obtained_marks' => $row['obtained_marks']],
            );
        }

        Notification::make()
            ->title(__('Marks saved'))
            ->success()
            ->send();
    }
}
