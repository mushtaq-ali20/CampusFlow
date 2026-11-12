<?php

namespace App\Filament\Resources\Settings\GradeTypes\Pages;

use App\Filament\Resources\Settings\GradeTypes\GradeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGradeTypes extends ManageRecords
{
    protected static string $resource = GradeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
