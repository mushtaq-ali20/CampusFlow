<?php

namespace App\Filament\Resources\ComplaintCategories\Pages;

use App\Filament\Resources\ComplaintCategories\ComplaintCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageComplaintCategories extends ManageRecords
{
    protected static string $resource = ComplaintCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
