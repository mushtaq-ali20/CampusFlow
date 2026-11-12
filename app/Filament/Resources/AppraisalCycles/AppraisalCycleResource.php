<?php

namespace App\Filament\Resources\AppraisalCycles;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\AppraisalCycles\Pages\ManageAppraisalCycles;
use App\Models\AppraisalCycle;
use App\Services\AppraisalService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rounds of evaluation, such as "Annual 2026". Starting a cycle makes a draft evaluation for every working
 * employee of the branch; closing it fixes its evaluations.
 */
class AppraisalCycleResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'appraisals.manage';

    protected static string $deletePermission = 'appraisals.manage';

    protected static ?string $model = AppraisalCycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 565;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('appraisals.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organization');
    }

    public static function getModelLabel(): string
    {
        return __('Evaluation cycle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Evaluation cycles');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->placeholder(__('Annual 2026'))->required()->maxLength(255)->unique(ignoreRecord: true)->columnSpanFull(),
                DatePicker::make('from_date')->label(__('From'))->required(),
                DatePicker::make('to_date')->label(__('Until'))->required()->afterOrEqual('from_date'),
                Toggle::make('is_closed')->label(__('Closed'))->helperText(__('The evaluations of a closed cycle can no longer be changed.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('appraisals'))
            ->defaultSort('from_date', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('bold'),
                TextColumn::make('from_date')
                    ->label(__('Period'))
                    ->formatStateUsing(fn (AppraisalCycle $record): string => $record->from_date->format('d M Y').' – '.$record->to_date->format('d M Y')),
                TextColumn::make('appraisals_count')->label(__('Evaluations'))->alignEnd(),
                IconColumn::make('is_closed')->label(__('Closed'))->boolean(),
            ])
            ->recordActions([
                Action::make('start')
                    ->label(__('Start evaluations'))
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (AppraisalCycle $record): bool => ! $record->is_closed && (auth()->user()?->can('appraisals.manage') ?? false))
                    ->requiresConfirmation()
                    ->modalDescription(__('Makes a draft evaluation for every working employee of the branch you are working on who has none in this cycle yet.'))
                    ->action(function (AppraisalCycle $record): void {
                        try {
                            $count = app(AppraisalService::class)->startForBranch($record, auth()->user()->activeBranchId(), auth()->user());
                        } catch (DomainException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title(trans_choice('{0} Every employee has an evaluation already|{1} :count evaluation started|[2,*] :count evaluations started', $count, ['count' => $count]))->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->visible(fn (AppraisalCycle $record): bool => $record->appraisals_count === 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAppraisalCycles::route('/'),
        ];
    }
}
