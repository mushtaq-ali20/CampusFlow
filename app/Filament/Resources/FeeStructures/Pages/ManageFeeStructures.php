<?php

namespace App\Filament\Resources\FeeStructures\Pages;

use App\Filament\Resources\FeeStructures\FeeStructureResource;
use App\Models\FeeStructure;
use App\Models\Year;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageFeeStructures extends ManageRecords
{
    protected static string $resource = FeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyStructure')
                ->label(__('Copy from another year'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->visible(fn (): bool => FeeStructureResource::canCreate())
                ->schema([
                    Select::make('from_year_id')
                        ->label(__('Copy from academic year'))
                        ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                        ->required(),
                    Select::make('to_year_id')
                        ->label(__('Copy to academic year'))
                        ->options(fn (): array => Year::query()->latest('id')->pluck('name', 'id')->all())
                        ->different('from_year_id')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $copied = 0;

                    FeeStructure::query()->where('year_id', $data['from_year_id'])->each(function (FeeStructure $structure) use ($data, &$copied): void {
                        $copy = FeeStructure::query()->firstOrCreate([
                            'school_class_id' => $structure->school_class_id,
                            'year_id' => $data['to_year_id'],
                            'campus_id' => $structure->campus_id,
                            'fee_head_id' => $structure->fee_head_id,
                        ], ['amount' => $structure->amount]);

                        $copied += $copy->wasRecentlyCreated ? 1 : 0;
                    });

                    Notification::make()
                        ->title(__(':count fee amounts copied.', ['count' => $copied]))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
