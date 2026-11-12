<?php

namespace App\Filament\Resources\AdmissionEnquiries\Widgets;

use App\Models\Year;
use App\Services\AdmissionEnquiryService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The seats of each class at each branch for the current academic year: capacity, admitted, registered enquiries
 * still in the funnel, and seats left.
 */
class SeatPlan extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Seat plan :year', ['year' => Year::current()?->name]))
            ->records(fn (): array => app(AdmissionEnquiryService::class)
                ->seatPlan(auth()->user(), Year::current())
                ->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => $row])
                ->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('campus')->label(__('Branch')),
                TextColumn::make('class')->label(__('Class')),
                TextColumn::make('capacity')->label(__('Capacity'))->placeholder('—')->alignEnd(),
                TextColumn::make('admitted')->label(__('Admitted'))->alignEnd(),
                TextColumn::make('inFunnel')->label(__('In admission steps'))->alignEnd(),
                TextColumn::make('seatsLeft')
                    ->label(__('Seats left'))
                    ->placeholder('—')
                    ->color(fn (?int $state): ?string => $state === 0 ? 'danger' : null)
                    ->alignEnd(),
            ]);
    }
}
