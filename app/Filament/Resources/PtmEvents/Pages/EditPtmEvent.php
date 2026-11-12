<?php

namespace App\Filament\Resources\PtmEvents\Pages;

use App\Filament\Resources\PtmEvents\PtmEventResource;
use App\Models\PtmEvent;
use App\Services\PtmService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPtmEvent extends EditRecord
{
    protected static string $resource = PtmEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * New students and sections get their slots; slots not yet held of removed sections are removed.
     */
    protected function afterSave(): void
    {
        /** @var PtmEvent $event */
        $event = $this->getRecord();

        app(PtmService::class)->generateMeetings($event);
    }
}
