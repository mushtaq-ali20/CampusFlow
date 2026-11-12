<?php

namespace App\Filament\Resources\Settings\SkillScales\Pages;

use App\Filament\Resources\Settings\SkillScales\SkillScaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkillScales extends ManageRecords
{
    protected static string $resource = SkillScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
