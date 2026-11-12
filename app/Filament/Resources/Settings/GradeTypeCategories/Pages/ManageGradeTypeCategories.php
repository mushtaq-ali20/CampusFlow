<?php

namespace App\Filament\Resources\Settings\GradeTypeCategories\Pages;

use App\Filament\Resources\Settings\GradeTypeCategories\GradeTypeCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGradeTypeCategories extends ManageRecords
{
    protected static string $resource = GradeTypeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
