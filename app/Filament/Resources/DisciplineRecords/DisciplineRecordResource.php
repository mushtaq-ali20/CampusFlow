<?php

namespace App\Filament\Resources\DisciplineRecords;

use App\Enums\AdmissionStatus;
use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\Resources\DisciplineRecords\Pages\ManageDisciplineRecords;
use App\Models\Admission;
use App\Models\DisciplineCategory;
use App\Models\DisciplineRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Guidance and violation: the merits and violations of the students, with their points. Staff with the discipline
 * permissions see and record them for all the students of their branch; teachers see and record them for the
 * students of the sections they teach. A record can be changed or deleted by staff who may record, or by its author.
 */
class DisciplineRecordResource extends Resource
{
    protected static ?string $model = DisciplineRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('discipline.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    /**
     * Staff who may record create records and change any record they see; teachers create records and change the
     * ones they wrote. The models have no policies, so every operation is decided here.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = auth()->user();
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };
        $mayRecord = $user?->can('discipline.record') ?? false;

        $allowed = match ($action) {
            'viewAny', 'view' => static::canAccess(),
            'create' => $mayRecord || ($user?->isTeacher() ?? false),
            'update', 'delete' => $mayRecord || ($user !== null && $record !== null && (int) $record->getAttribute('recorded_by') === (int) $user->id),
            default => $mayRecord,
        };

        return $allowed ? parent::getAuthorizationResponse($action, $record) : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Guidance');
    }

    public static function getNavigationLabel(): string
    {
        return __('Guidance & violation');
    }

    public static function getModelLabel(): string
    {
        return __('Discipline record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Discipline records');
    }

    /**
     * Staff attached to a branch only see the records of their branch, and teachers without the discipline
     * permission only see those of the sections they teach.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->visibleTo($user)
            ->when($user !== null && ! $user->can('discipline.view'), fn (Builder $query) => $query->ofSectionsTaughtBy($user));
    }

    /**
     * The sections of the current academic year where the user records discipline: all the sections of their branch,
     * or for a teacher without the discipline permission, the sections they teach.
     *
     * @return Builder<Section>
     */
    public static function sectionsFor(?User $user): Builder
    {
        return Section::query()
            ->visibleTo($user)
            ->where('year_id', Year::current()?->id)
            ->when($user !== null && ! $user->can('discipline.view'), fn (Builder $query) => $query->taughtBy($user));
    }

    /**
     * @return array<int, string>
     */
    public static function sectionOptions(): array
    {
        return static::sectionsFor(auth()->user())
            ->with(['schoolClass', 'campus'])
            ->get()
            ->sortBy(fn (Section $section): string => $section->campus->name.str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->campus->name.' - '.$section->full_name])
            ->all();
    }

    /**
     * The students of the section, if the user records discipline for it.
     *
     * @return array<int, string>
     */
    public static function admissionOptions(?int $sectionId): array
    {
        if ($sectionId === null || ! static::sectionsFor(auth()->user())->whereKey($sectionId)->exists()) {
            return [];
        }

        return Admission::query()
            ->with('student')
            ->where('section_id', $sectionId)
            ->where('status', AdmissionStatus::Active)
            ->get()
            ->sortBy(fn (Admission $admission): string => $admission->student->name)
            ->mapWithKeys(fn (Admission $admission): array => [$admission->id => $admission->student->name.' ('.$admission->admission_number.')'])
            ->all();
    }

    /**
     * The active categories, merits and violations apart, with their points.
     *
     * @return array<string, array<int, string>>
     */
    public static function categoryOptions(): array
    {
        return DisciplineCategory::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (DisciplineCategory $category): string => $category->kind->getLabel())
            ->map(fn ($categories): array => $categories
                ->mapWithKeys(fn (DisciplineCategory $category): array => [$category->id => sprintf('%s (%+d)', $category->name, $category->signedPoints())])
                ->all())
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => static::sectionOptions())
                    ->in(fn (): array => array_keys(static::sectionOptions()))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('admission_id', null)),
                Select::make('admission_id')
                    ->label(__('Student'))
                    ->options(fn (Get $get): array => static::admissionOptions(filled($get('section_id')) ? (int) $get('section_id') : null))
                    ->in(fn (Get $get): array => array_keys(static::admissionOptions(filled($get('section_id')) ? (int) $get('section_id') : null)))
                    ->searchable()
                    ->required(),
                Select::make('discipline_category_id')
                    ->label(__('Category'))
                    ->options(fn (): array => static::categoryOptions())
                    ->searchable()
                    ->required(),
                DatePicker::make('date')
                    ->label(__('Date'))
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                Textarea::make('remarks')
                    ->label(__('Remarks'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['admission.student', 'section.schoolClass', 'category', 'recordedBy']))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('admission.student.name')
                    ->label(__('Student'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('admission.student.user', fn (Builder $users) => $users
                        ->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%"))),
                TextColumn::make('section.full_name')
                    ->label(__('Section')),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->description(fn (DisciplineRecord $record): ?string => $record->remarks)
                    ->wrap(),
                TextColumn::make('points')
                    ->label(__('Points'))
                    ->formatStateUsing(fn (int $state): string => sprintf('%+d', $state))
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('consequence')
                    ->label(__('What follows'))
                    ->badge()
                    ->placeholder(__('Merit')),
                TextColumn::make('recordedBy.name')
                    ->label(__('Recorded by'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('Kind'))
                    ->options(DisciplineKind::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas('category', fn (Builder $categories) => $categories->where('kind', $data['value'])),
                    )),
                SelectFilter::make('discipline_category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name'),
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn (): array => static::sectionOptions())
                    ->searchable(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('To')),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '<=', $date))),
            ])
            ->recordActions([
                SendWhatsAppAction::make()
                    ->student(fn (DisciplineRecord $record): Student => $record->admission->student)
                    ->message(fn (DisciplineRecord $record): string => static::parentMessage($record))
                    ->aboutRecord(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * The WhatsApp message that tells the parents about the record.
     */
    public static function parentMessage(DisciplineRecord $record): string
    {
        $replace = [
            'student' => $record->admission->student->name,
            'section' => $record->section->full_name,
            'category' => $record->category->name,
            'date' => $record->date->format('d M Y'),
        ];

        $lines = [__('Dear parent,')];

        $lines[] = $record->points >= 0
            ? __('We are pleased to inform you that :student (:section) received a merit for ":category" on :date.', $replace)
            : __('This is to inform you that :student (:section) was recorded for ":category" on :date.', $replace);

        if (filled($record->remarks)) {
            $lines[] = __('Remarks: :remarks', ['remarks' => $record->remarks]);
        }

        if ($record->consequence === DisciplineConsequence::ParentCalledIn) {
            $lines[] = __('Please visit the school to meet the class teacher.');
        }

        $lines[] = (string) config('campusflow.company_name');

        return implode("\n\n", $lines);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDisciplineRecords::route('/'),
        ];
    }
}
