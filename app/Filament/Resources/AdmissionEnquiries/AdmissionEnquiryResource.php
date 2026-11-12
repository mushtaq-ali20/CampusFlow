<?php

namespace App\Filament\Resources\AdmissionEnquiries;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\AdmissionEnquiries\Pages\CreateAdmissionEnquiry;
use App\Filament\Resources\AdmissionEnquiries\Pages\EditAdmissionEnquiry;
use App\Filament\Resources\AdmissionEnquiries\Pages\ListAdmissionEnquiries;
use App\Filament\Resources\AdmissionEnquiries\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Admission;
use App\Models\AdmissionEnquiry;
use App\Models\SchoolClass;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admission enquiries, from the first contact with a family to the admission of their child. See
 * App\Services\AdmissionEnquiryService for the admission steps.
 */
class AdmissionEnquiryResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'enquiries.manage';

    protected static string $deletePermission = 'enquiries.manage';

    protected static ?string $model = AdmissionEnquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'enquiry_number';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('enquiries.view') ?? false;
    }

    /**
     * An enquiry that led to an admission is kept.
     */
    public static function canDelete(Model $record): bool
    {
        return $record->getAttribute('admission_id') === null && parent::canDelete($record);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Front desk');
    }

    public static function getModelLabel(): string
    {
        return __('Admission enquiry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Admission enquiries');
    }

    /**
     * Staff attached to a branch only see the enquiries of their branch.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    /**
     * @return array<int, string>
     */
    public static function siblingOptions(string $search): array
    {
        return Admission::query()
            ->visibleTo(auth()->user())
            ->whereHas('student.user', fn (Builder $users) => $users
                ->where('firstname', 'like', "%{$search}%")
                ->orWhere('lastname', 'like', "%{$search}%"))
            ->with(['student', 'section.schoolClass'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Admission $admission): array => [$admission->id => $admission->student->name.' · '.$admission->section?->full_name.' · '.$admission->admission_number])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Child'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('student_firstname')
                            ->label(__('First name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('student_lastname')
                            ->label(__('Last name'))
                            ->required()
                            ->maxLength(255),
                        Radio::make('gender_id')
                            ->label(__('Gender'))
                            ->options([0 => __('Other'), 1 => __('Female'), 2 => __('Male')])
                            ->default(0)
                            ->inline()
                            ->required(),
                        DatePicker::make('birthdate')
                            ->label(__('Date of birth'))
                            ->maxDate(now()),
                        TextInput::make('previous_school')
                            ->label(__('Previous school'))
                            ->maxLength(255),
                    ]),
                Section::make(__('Seat wanted'))
                    ->columns(3)
                    ->schema([
                        Select::make('campus_id')
                            ->label(__('Branch'))
                            ->options(fn (): array => SectionResource::branchOptions())
                            ->default(fn (): ?int => auth()->user()?->activeBranchId())
                            ->required(),
                        Select::make('year_id')
                            ->label(__('Academic Year'))
                            ->options(fn (): array => Year::query()->orderByDesc('start')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Year::current()?->id)
                            ->required(),
                        Select::make('school_class_id')
                            ->label(__('Class'))
                            ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all())
                            ->required(),
                    ]),
                Section::make(__('Guardian'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('guardian_name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('guardian_relation')
                            ->label(__('Relation'))
                            ->options(['Father' => __('Father'), 'Mother' => __('Mother'), 'Guardian' => __('Guardian')]),
                        TextInput::make('guardian_phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->placeholder('0300-1234567')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('guardian_email')
                            ->label(__('Email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label(__('Address'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Follow-up'))
                    ->columns(2)
                    ->schema([
                        Select::make('source')
                            ->label(__('How they heard of us'))
                            ->options(EnquirySource::class)
                            ->default(EnquirySource::WalkIn)
                            ->required(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(EnquiryStatus::followUpOptions())
                            ->default(EnquiryStatus::InProcess->value)
                            ->required()
                            ->visible(fn (?AdmissionEnquiry $record): bool => $record === null || $record->status->isOpen() || $record->status === EnquiryStatus::NotInterested),
                        DatePicker::make('next_follow_up_on')
                            ->label(__('Next follow-up')),
                        Select::make('sibling_admission_id')
                            ->label(__('Brother or sister at the school'))
                            ->helperText(__('Their fee dues are checked before admission.'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::siblingOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ($admission = Admission::query()->with(['student', 'section.schoolClass'])->find($value)) ? $admission->student->name.' · '.$admission->section?->full_name : null),
                        Textarea::make('remarks')
                            ->label(__('Remarks'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Documents received'))
                    ->visibleOn('edit')
                    ->schema([
                        CheckboxList::make('documents_received')
                            ->hiddenLabel()
                            ->options(fn (): array => collect(AdmissionEnquiry::documentOptions())
                                ->map(fn (string $label, string $key): string => AdmissionEnquiry::DOCUMENTS[$key] ? $label.' *' : $label)
                                ->all())
                            ->helperText(__('* required for the document review'))
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['campus', 'schoolClass']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('enquiry_number')
                    ->label(__('Enquiry No.'))
                    ->searchable(),
                TextColumn::make('student_name')
                    ->label(__('Child'))
                    ->searchable(['student_firstname', 'student_lastname'])
                    ->description(fn (AdmissionEnquiry $record): string => $record->schoolClass->name),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->toggleable(),
                TextColumn::make('guardian_name')
                    ->label(__('Guardian'))
                    ->description(fn (AdmissionEnquiry $record): string => $record->guardian_phone)
                    ->searchable(['guardian_name', 'guardian_phone']),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('stage')
                    ->label(__('Waiting for'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('next_follow_up_on')
                    ->label(__('Next follow-up'))
                    ->date()
                    ->color(fn (AdmissionEnquiry $record): ?string => $record->next_follow_up_on?->isBefore(today()) && $record->status->isOpen() ? 'danger' : null)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => SchoolClass::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('source')
                    ->label(__('Source'))
                    ->options(EnquirySource::class),
                SelectFilter::make('year_id')
                    ->label(__('Academic Year'))
                    ->options(fn (): array => Year::query()->orderByDesc('start')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Year::current()?->id),
            ])
            ->recordActions([
                EditAction::make()->label(__('Open')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissionEnquiries::route('/'),
            'create' => CreateAdmissionEnquiry::route('/create'),
            'edit' => EditAdmissionEnquiry::route('/{record}/edit'),
        ];
    }
}
