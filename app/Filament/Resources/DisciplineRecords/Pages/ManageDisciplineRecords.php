<?php

namespace App\Filament\Resources\DisciplineRecords\Pages;

use App\Filament\Resources\DisciplineRecords\DisciplineRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDisciplineRecords extends ManageRecords
{
    protected static string $resource = DisciplineRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Record'))
                ->mutateDataUsing(fn (array $data): array => [...$data, 'recorded_by' => auth()->id()]),
        ];
    }
}
