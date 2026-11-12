<?php

namespace App\Filament\Resources\Settings\ContactRelationships\Pages;

use App\Filament\Resources\Settings\ContactRelationships\ContactRelationshipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContactRelationships extends ManageRecords
{
    protected static string $resource = ContactRelationshipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
