<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSection extends ViewRecord
{
    use ShowsRecordHeader;

    protected static string $resource = SectionResource::class;

    protected function recordHeader(): array
    {
        /** @var Section $section */
        $section = $this->getRecord();

        return [
            'title' => $section->full_name,
            'subtitle' => collect([$section->campus?->name, $section->year?->name, trans_choice(':count student|:count students', $section->admissions()->count())])->filter()->implode(' · '),
            'badge' => null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
