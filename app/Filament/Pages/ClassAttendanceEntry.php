<?php

namespace App\Filament\Pages;

use App\Enums\AdmissionStatus;
use App\Enums\ClassAttendanceStatus;
use App\Models\Admission;
use App\Models\ClassAttendance;
use App\Models\Section;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily attendance of a section. Attendance follows the class, not the subjects: the class teacher
 * of the section, or staff with the attendance permission, take it once a day.
 */
class ClassAttendanceEntry extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 6;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('attendance.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Class attendance');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Class attendance');
    }

    public function canTakeAttendance(): bool
    {
        return (auth()->user()?->can('attendance.edit') ?? false) || (auth()->user()?->isTeacher() ?? false);
    }

    /**
     * The sections the user takes attendance for: all the sections they can see, or for a teacher
     * without the permission, the sections where they are the class teacher.
     *
     * @return Builder<Section>
     */
    protected function sections(): Builder
    {
        $user = auth()->user();

        return Section::query()
            ->visibleTo($user)
            ->when(! $user->can('attendance.view'), fn (Builder $query) => $query->where('class_teacher_id', $user->id));
    }

    public function mount(): void
    {
        $this->form->fill(['date' => now()->toDateString(), 'rows' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->afterStateUpdated(fn () => $this->loadRows()),
                DatePicker::make('date')
                    ->label(__('Date'))
                    ->maxDate(now())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadRows()),
                Repeater::make('rows')
                    ->label(__('Students'))
                    ->schema([
                        Hidden::make('admission_id'),
                        TextInput::make('name')
                            ->label(__('Student'))
                            ->disabled()
                            ->dehydrated(false),
                        ToggleButtons::make('status')
                            ->label(__('Attendance'))
                            ->options(ClassAttendanceStatus::class)
                            ->inline()
                            ->required(),
                        TextInput::make('remarks')
                            ->label(__('Remarks'))
                            ->maxLength(255),
                    ])
                    ->columns(['default' => 1, 'md' => 3])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (): bool => filled($this->data['section_id'] ?? null)),
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
                                ->label(__('Save attendance'))
                                ->submit('save')
                                ->visible(fn (): bool => $this->canTakeAttendance() && filled($this->data['section_id'] ?? null)),
                        ]),
                    ]),
            ]);
    }

    /**
     * Lists the active students of the section with the attendance already taken for the date.
     */
    public function loadRows(): void
    {
        $sectionId = $this->data['section_id'] ?? null;
        $date = $this->data['date'] ?? null;

        if (blank($sectionId) || blank($date) || ! $this->sections()->whereKey($sectionId)->exists()) {
            $this->data['rows'] = [];

            return;
        }

        $recorded = ClassAttendance::query()
            ->where('section_id', $sectionId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('admission_id');

        $this->data['rows'] = Admission::query()
            ->with('student')
            ->where('section_id', $sectionId)
            ->where('status', AdmissionStatus::Active)
            ->get()
            ->sortBy(fn (Admission $admission): string => $admission->student->name)
            ->map(fn (Admission $admission): array => [
                'admission_id' => $admission->id,
                'name' => $admission->student->name,
                'status' => ($recorded[$admission->id]->status ?? ClassAttendanceStatus::Present)->value,
                'remarks' => $recorded[$admission->id]->remarks ?? null,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        abort_unless($this->canTakeAttendance(), 403);

        $state = $this->form->getState();
        $section = $this->sections()->findOrFail($state['section_id']);
        $date = Carbon::parse($state['date']);
        $admissions = Admission::query()->where('section_id', $section->id)->where('status', AdmissionStatus::Active)->pluck('id');

        foreach ($state['rows'] as $row) {
            if (! $admissions->contains((int) $row['admission_id'])) {
                continue;
            }

            ClassAttendance::query()->updateOrCreate(
                ['admission_id' => $row['admission_id'], 'date' => $date->toDateString()],
                [
                    'section_id' => $section->id,
                    'status' => $row['status'],
                    'remarks' => $row['remarks'] ?? null,
                    'recorded_by' => auth()->id(),
                ],
            );
        }

        Notification::make()
            ->title(__('Attendance saved'))
            ->success()
            ->send();
    }
}
