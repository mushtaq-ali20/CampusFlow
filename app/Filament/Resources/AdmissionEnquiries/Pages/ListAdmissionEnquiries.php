<?php

namespace App\Filament\Resources\AdmissionEnquiries\Pages;

use App\Enums\EnquiryStage;
use App\Enums\EnquiryStatus;
use App\Filament\Resources\AdmissionEnquiries\AdmissionEnquiryResource;
use App\Filament\Resources\AdmissionEnquiries\Widgets\EnquiryOverview;
use App\Filament\Resources\AdmissionEnquiries\Widgets\SeatPlan;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissionEnquiries extends ListRecords
{
    protected static string $resource = AdmissionEnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New enquiry')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EnquiryOverview::class,
            SeatPlan::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'follow_up' => Tab::make(__('Follow-up'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [EnquiryStatus::InProcess, EnquiryStatus::Hold, EnquiryStatus::Interested])),
            'registered' => Tab::make(__('In admission steps'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', EnquiryStatus::Registered)->where('stage', '!=', EnquiryStage::ReadyToAdmit)),
            'ready' => Tab::make(__('Ready to admit'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stage', EnquiryStage::ReadyToAdmit)),
            'admitted' => Tab::make(__('Admitted'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', EnquiryStatus::Admitted)),
            'not_interested' => Tab::make(__('Not interested'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', EnquiryStatus::NotInterested)),
            'all' => Tab::make(__('All')),
        ];
    }
}
