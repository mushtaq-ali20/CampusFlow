<?php

namespace App\Filament\Resources\Complaints\Pages;

use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Log complaint')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('Open'))
                ->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'mine' => Tab::make(__('Assigned to me'))
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->where('assigned_to', auth()->id())),
            'overdue' => Tab::make(__('Past time limit'))
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->where('due_at', '<', now())),
            'resolved' => Tab::make(__('Resolved'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ComplaintStatus::Resolved)),
            'all' => Tab::make(__('All')),
        ];
    }
}
