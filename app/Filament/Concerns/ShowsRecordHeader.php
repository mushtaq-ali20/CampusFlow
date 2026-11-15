<?php

namespace App\Filament\Concerns;

use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * For a view page of a record: a card with the record's name, an optional status badge and one line of facts,
 * shown above its details. The page then has no heading of its own, as the card carries the name.
 */
trait ShowsRecordHeader
{
    /**
     * @return array{title: string, subtitle: ?string, badge: ?array{label: string, color: string}}
     */
    abstract protected function recordHeader(): array;

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.components.record-header')->viewData(fn (): array => ['header' => $this->recordHeader()]),
            $this->hasInfolist() ? $this->getInfolistContentComponent() : $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }
}
