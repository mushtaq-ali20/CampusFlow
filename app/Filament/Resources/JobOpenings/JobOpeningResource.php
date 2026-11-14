<?php

namespace App\Filament\Resources\JobOpenings;

use App\Enums\ApplicantStage;
use App\Enums\EmploymentType;
use App\Enums\OpeningStatus;
use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\JobApplicants\JobApplicantResource;
use App\Filament\Resources\JobOpenings\Pages\ManageJobOpenings;
use App\Models\Campus;
use App\Models\JobOpening;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The vacancies of the school. Applicants are entered against an opening, which closes by itself when every
 * position is filled.
 */
class JobOpeningResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'recruitment.manage';

    protected static string $deletePermission = 'recruitment.manage';

    protected static ?string $model = JobOpening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 550;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('recruitment.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Job opening');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Job openings');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label(__('Job title'))->placeholder(__('Maths Teacher'))->required()->maxLength(255),
                TextInput::make('department')->label(__('Department'))->maxLength(255),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(function (): array {
                        $own = auth()->user()?->campus_id;

                        return Campus::query()->when($own, fn (Builder $query) => $query->whereKey($own))->get()->pluck('name', 'id')->all();
                    })
                    ->default(fn (): ?int => auth()->user()?->activeBranchId())
                    ->required(),
                Select::make('employment_type')->label(__('Employment'))->options(EmploymentType::class)->default(EmploymentType::Permanent)->required(),
                TextInput::make('positions')->label(__('Positions'))->integer()->minValue(1)->default(1)->required(),
                DatePicker::make('closes_on')->label(__('Applications until')),
                Select::make('status')->label(__('Status'))->options(OpeningStatus::class)->default(OpeningStatus::Open)->required(),
                Textarea::make('description')->label(__('Requirements'))->rows(3)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('campus')->withCount([
                'applicants',
                'applicants as hired_count' => fn (Builder $applicants) => $applicants->where('stage', ApplicantStage::Hired),
            ]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('Job title'))->description(fn (JobOpening $record): ?string => $record->department)->searchable()->weight('bold'),
                TextColumn::make('campus.name')->label(__('Branch')),
                TextColumn::make('employment_type')->label(__('Employment'))->badge()->color('gray'),
                TextColumn::make('applicants_count')->label(__('Applicants'))->alignEnd(),
                TextColumn::make('hired_count')
                    ->label(__('Hired'))
                    ->formatStateUsing(fn (JobOpening $record): string => $record->hired_count.' / '.$record->positions)
                    ->alignEnd(),
                TextColumn::make('closes_on')->label(__('Applications until'))->date('d M Y')->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(OpeningStatus::class)->default(OpeningStatus::Open->value),
            ])
            ->recordActions([
                Action::make('applicants')
                    ->label(__('Applicants'))
                    ->icon(Heroicon::OutlinedUsers)
                    ->button()
                    ->color('gray')
                    ->url(fn (JobOpening $record): string => JobApplicantResource::getUrl('index', ['tableFilters' => ['job_opening_id' => ['value' => $record->id]]])),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()->visible(fn (JobOpening $record): bool => $record->applicants_count === 0),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobOpenings::route('/'),
        ];
    }
}
