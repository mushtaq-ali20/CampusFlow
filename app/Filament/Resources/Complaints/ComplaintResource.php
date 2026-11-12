<?php

namespace App\Filament\Resources\Complaints;

use App\Enums\ComplainantType;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Complaints\Pages\CreateComplaint;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Resources\Complaints\RelationManagers\UpdatesRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Complaints from parents, students and staff. Staff with the complaints permissions see all the complaints of their
 * branch; anyone a complaint is assigned to sees it, comments on it and resolves it. Complaints are never deleted.
 */
class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'complaint_number';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('complaints.view') || Complaint::query()->where('assigned_to', $user->id)->exists());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = match (true) {
            $action instanceof BackedEnum => $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        $allowed = match ($action) {
            'viewAny', 'view' => static::canAccess(),
            'create', 'update' => auth()->user()?->can('complaints.manage') ?? false,
            default => false,
        };

        return $allowed ? parent::getAuthorizationResponse($action, $record) : Response::deny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Front desk');
    }

    public static function getModelLabel(): string
    {
        return __('Complaint');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Complaints');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = $user ? static::getEloquentQuery()->open()->where('due_at', '<', now())->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Past their time limit');
    }

    /**
     * Staff with the complaints permission see the complaints of their branch; others see those assigned to them.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user?->can('complaints.view'),
                fn (Builder $query) => $query->visibleTo($user),
                fn (Builder $query) => $query->where('assigned_to', $user?->id),
            );
    }

    /**
     * The staff a complaint can be given to: users with a role, and teachers.
     *
     * @return array<int, string>
     */
    public static function staffOptions(): array
    {
        return User::query()
            ->where(fn (Builder $users) => $users->whereHas('roles')->orWhereIn('id', Teacher::query()->select('id')))
            ->get()
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Complaint'))
                    ->columns(3)
                    ->schema([
                        Select::make('campus_id')
                            ->label(__('Branch'))
                            ->options(fn (): array => SectionResource::branchOptions())
                            ->default(fn (): ?int => auth()->user()?->activeBranchId())
                            ->required(),
                        Select::make('complaint_category_id')
                            ->label(__('Category'))
                            ->options(fn (): array => ComplaintCategory::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->required(),
                        Select::make('priority')
                            ->label(__('Priority'))
                            ->options(ComplaintPriority::class)
                            ->default(ComplaintPriority::Medium)
                            ->required(),
                        TextInput::make('subject')
                            ->label(__('Subject'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Raised by'))
                    ->columns(3)
                    ->schema([
                        Select::make('complainant_type')
                            ->label(__('Raised by'))
                            ->options(ComplainantType::class)
                            ->default(ComplainantType::Parent)
                            ->required(),
                        TextInput::make('complainant_name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('complainant_phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(30),
                        Select::make('student_id')
                            ->label(__('About the student'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => AdmissionResource::searchStudents($search))
                            ->getOptionLabelUsing(fn ($value): ?string => Student::query()->find($value)?->name)
                            ->columnSpan(2),
                        Select::make('assigned_to')
                            ->label(__('Assign to'))
                            ->helperText(__('Leave empty to give it to the person who handles the category.'))
                            ->options(fn (): array => static::staffOptions())
                            ->searchable(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextEntry::make('status')->label(__('Status'))->badge(),
                        TextEntry::make('priority')->label(__('Priority'))->badge(),
                        TextEntry::make('assignee.name')->label(__('Assigned to'))->placeholder(__('Nobody')),
                        TextEntry::make('due_at')
                            ->label(__('Resolve by'))
                            ->dateTime()
                            ->color(fn (Complaint $record): ?string => $record->isOverdue() ? 'danger' : null)
                            ->hidden(fn (Complaint $record): bool => ! $record->status->isOpen()),
                        TextEntry::make('resolved_at')
                            ->label(__('Resolved on'))
                            ->dateTime()
                            ->visible(fn (Complaint $record): bool => ! $record->status->isOpen()),
                        TextEntry::make('category.name')->label(__('Category')),
                        TextEntry::make('campus.name')->label(__('Branch')),
                        TextEntry::make('complainant_name')
                            ->label(__('Raised by'))
                            ->formatStateUsing(fn (Complaint $record): string => $record->complainant_name.' ('.$record->complainant_type->getLabel().')')
                            ->helperText(fn (Complaint $record): ?string => $record->complainant_phone),
                        TextEntry::make('student.name')->label(__('Student'))->placeholder('—'),
                        TextEntry::make('subject')->label(__('Subject'))->columnSpanFull(),
                        TextEntry::make('description')->label(__('Description'))->columnSpanFull(),
                        TextEntry::make('resolution')
                            ->label(__('Resolution'))
                            ->columnSpanFull()
                            ->visible(fn (Complaint $record): bool => filled($record->resolution)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'assignee', 'student']))
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('complaint_number')
                    ->label(__('No.'))
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->description(fn (Complaint $record): string => $record->complainant_name.' · '.$record->complainant_type->getLabel())
                    ->searchable(['subject', 'complainant_name'])
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label(__('Category')),
                TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge(),
                TextColumn::make('assignee.name')
                    ->label(__('Assigned to'))
                    ->placeholder(__('Nobody')),
                TextColumn::make('due_at')
                    ->label(__('Resolve by'))
                    ->dateTime('d M H:i')
                    ->description(fn (Complaint $record): ?string => $record->isOverdue() ? __('Overdue') : null)
                    ->color(fn (Complaint $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Logged'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('complaint_category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name'),
                SelectFilter::make('priority')
                    ->label(__('Priority'))
                    ->options(ComplaintPriority::class),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ComplaintStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->label(__('Open')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UpdatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'create' => CreateComplaint::route('/create'),
            'view' => ViewComplaint::route('/{record}'),
        ];
    }
}
