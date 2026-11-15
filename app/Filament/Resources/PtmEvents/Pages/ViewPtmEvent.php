<?php

namespace App\Filament\Resources\PtmEvents\Pages;

use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\PtmEvents\PtmEventResource;
use App\Models\PtmEvent;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * A meeting day with the slot of each family, where the class teachers record their meetings.
 */
class ViewPtmEvent extends ViewRecord
{
    use ShowsRecordHeader;

    protected static string $resource = PtmEventResource::class;

    protected function recordHeader(): array
    {
        /** @var PtmEvent $event */
        $event = $this->getRecord();

        return [
            'title' => $event->title,
            'subtitle' => $event->date->translatedFormat('l d F Y').' · '.$event->campus?->name,
            'badge' => null,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        /** @var PtmEvent $event */
        $event = $this->getRecord();

        return $event->title.' · '.$event->date->translatedFormat('l d F Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 4])
                    ->schema([
                        TextEntry::make('campus.name')->label(__('Branch')),
                        TextEntry::make('starts_at')
                            ->label(__('First slot at'))
                            ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->format('h:i A')),
                        TextEntry::make('slot_minutes')->label(__('Minutes per family')),
                        TextEntry::make('sections.name')
                            ->label(__('Sections'))
                            ->state(fn (PtmEvent $record): array => $record->sections()->with('schoolClass')->get()->map(fn ($section): string => $section->full_name)->all())
                            ->badge(),
                        TextEntry::make('notes')->label(__('Notes'))->columnSpanFull()->visible(fn (PtmEvent $record): bool => filled($record->notes)),
                    ]),
            ]);
    }
}
