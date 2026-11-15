<?php

namespace App\Filament\Resources\SchoolClasses\Pages;

use App\Filament\Concerns\ShowsRecordHeader;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Models\SchoolClass;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSchoolClass extends ViewRecord
{
    use ShowsRecordHeader;

    protected static string $resource = SchoolClassResource::class;

    protected function recordHeader(): array
    {
        /** @var SchoolClass $class */
        $class = $this->getRecord();

        return [
            'title' => $class->name,
            'subtitle' => __('Promotion order :order', ['order' => $class->order]).' · '.trans_choice(':count subject|:count subjects', $class->subjects()->count()).' · '.trans_choice(':count section|:count sections', $class->sections()->count()),
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
