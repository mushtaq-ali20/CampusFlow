<?php

namespace App\Filament\Resources\AdmissionEnquiries\Pages;

use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Models\AdmissionEnquiry;
use App\Services\AdmissionEnquiryService;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmissionEnquiry extends CreateRecord
{
    protected static string $resource = AdmissionEnquiryResource::class;

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
        /** @var AdmissionEnquiry $enquiry */
        $enquiry = $this->getRecord();

        app(AdmissionEnquiryService::class)->log($enquiry, __('Enquiry received.'), auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
