<?php

namespace App\Filament\Resources\PtmEvents\Pages;

use App\Filament\Resources\PtmEvents\PtmEventResource;
use App\Models\PtmEvent;
use App\Services\PtmService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePtmEvent extends CreateRecord
{
    protected static string $resource = PtmEventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        /** @var PtmEvent $event */
        $event = $this->getRecord();
        $slots = app(PtmService::class)->generateMeetings($event);

        Notification::make()->title(__(':count slots created', ['count' => $slots]))->success()->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
