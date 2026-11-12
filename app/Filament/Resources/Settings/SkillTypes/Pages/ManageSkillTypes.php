<?php

namespace App\Filament\Resources\Settings\SkillTypes\Pages;

use App\Filament\Resources\Settings\SkillTypes\SkillTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkillTypes extends ManageRecords
{
    protected static string $resource = SkillTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
