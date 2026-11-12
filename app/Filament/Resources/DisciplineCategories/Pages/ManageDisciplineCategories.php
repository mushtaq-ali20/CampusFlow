<?php

namespace App\Filament\Resources\DisciplineCategories\Pages;

use App\Filament\Resources\DisciplineCategories\DisciplineCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDisciplineCategories extends ManageRecords
{
    protected static string $resource = DisciplineCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => DisciplineCategoryResource::withoutConsequenceForMerits($data)),
        ];
    }
}
