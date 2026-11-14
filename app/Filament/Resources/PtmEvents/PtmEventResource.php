<?php

namespace App\Filament\Resources\PtmEvents;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\PtmEvents\Pages\CreatePtmEvent;
use App\Filament\Resources\PtmEvents\Pages\EditPtmEvent;
use App\Filament\Resources\PtmEvents\Pages\ListPtmEvents;
use App\Filament\Resources\PtmEvents\Pages\ViewPtmEvent;
use App\Filament\Resources\PtmEvents\RelationManagers\MeetingsRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\PtmEvent;
use App\Models\Section;
use App\Models\User;
use App\Models\Year;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Parent teacher meeting days. Staff with the PTM permissions plan them for some sections of a branch; each student
 * gets a time slot with their class teacher. Class teachers see the days of their sections and record their meetings.
 */
class PtmEventResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'ptm.manage';

    protected static string $deletePermission = 'ptm.manage';

    protected static ?string $model = PtmEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can('ptm.view') ?? false) || ($user?->isTeacher() ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Front desk');
    }

    public static function getNavigationLabel(): string
    {
        return __('Parent teacher meetings');
    }

    public static function getModelLabel(): string
    {
        return __('Parent teacher meeting');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Parent teacher meetings');
    }

    /**
     * Staff attached to a branch see the days of their branch; class teachers without the PTM permission see the
     * days of their sections.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->visibleTo($user)
            ->when($user !== null && ! $user->can('ptm.view'), fn (Builder $query) => static::forClassTeacher($query, $user));
    }

    /**
     * @param  Builder<PtmEvent>  $query
     * @return Builder<PtmEvent>
     */
    public static function forClassTeacher(Builder $query, User $user): Builder
    {
        return $query->whereHas('sections', fn (Builder $sections) => $sections->where('class_teacher_id', $user->id));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make()->columns(3)->columnSpanFull()->schema([
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->placeholder(__('Mid-term PTM'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('campus_id')
                        ->label(__('Branch'))
                        ->options(fn (): array => SectionResource::branchOptions())
                        ->default(fn (): ?int => auth()->user()?->activeBranchId())
                        ->required()
                        ->live(),
                    Select::make('year_id')
                        ->label(__('Academic Year'))
                        ->options(fn (): array => Year::query()->orderByDesc('start')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => Year::current()?->id)
                        ->required()
                        ->live(),
                    DatePicker::make('date')
                        ->label(__('Date'))
                        ->required(),
                    TimePicker::make('starts_at')
                        ->label(__('First slot at'))
                        ->seconds(false)
                        ->default('09:00')
                        ->required(),
                    TextInput::make('slot_minutes')
                        ->label(__('Minutes per family'))
                        ->integer()
                        ->minValue(5)
                        ->maxValue(60)
                        ->default(10)
                        ->required(),
                    Select::make('sections')
                        ->label(__('Sections'))
                        ->helperText(__('Each student of these sections gets a slot with the class teacher. Saving adds the slots of new students and sections.'))
                        ->multiple()
                        ->relationship(
                            'sections',
                            'name',
                            modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                ->where('campus_id', $get('campus_id'))
                                ->where('year_id', $get('year_id'))
                                ->with('schoolClass'),
                        )
                        ->getOptionLabelFromRecordUsing(fn (Section $record): string => $record->full_name)
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('campus')
                ->withCount([
                    'sections',
                    'meetings',
                    'meetings as met_count' => fn (Builder $meetings) => $meetings->where('status', 'met'),
                    'meetings as open_actions_count' => fn (Builder $meetings) => $meetings->whereNotNull('action_items')->whereNull('action_done_at'),
                ]))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('Title'))->searchable(),
                TextColumn::make('date')->label(__('Date'))->date('D d M Y')->sortable(),
                TextColumn::make('campus.name')->label(__('Branch')),
                TextColumn::make('sections_count')->label(__('Sections'))->alignEnd(),
                TextColumn::make('meetings_count')->label(__('Families'))->alignEnd(),
                TextColumn::make('met_count')->label(__('Met'))->alignEnd(),
                TextColumn::make('open_actions_count')
                    ->label(__('Open actions'))
                    ->color(fn (int $state): ?string => $state > 0 ? 'warning' : null)
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('Open'))->button()->color('gray'),
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MeetingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPtmEvents::route('/'),
            'create' => CreatePtmEvent::route('/create'),
            'view' => ViewPtmEvent::route('/{record}'),
            'edit' => EditPtmEvent::route('/{record}/edit'),
        ];
    }
}
