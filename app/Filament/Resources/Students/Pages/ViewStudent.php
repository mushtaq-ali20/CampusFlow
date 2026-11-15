<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Actions\SendWhatsAppAction;
use App\Filament\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\ClassAttendancesRelationManager;
use App\Filament\Resources\Students\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Students\RelationManagers\DisciplineRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\FeeVouchersRelationManager;
use App\Filament\Resources\Students\RelationManagers\ParentMessagesRelationManager;
use App\Filament\Resources\Students\RelationManagers\SubjectResultsRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Students\Widgets\StudentOverview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The student's profile: their details and comments, a summary of the year, then their attendance, results, fees,
 * discipline and messages to parents in tabs, each shown to the staff allowed to see it.
 */
class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * The tabs of the student's record, by key.
     *
     * @var array<string, class-string<RelationManager>>
     */
    public const RECORD_TABS = [
        'attendance' => ClassAttendancesRelationManager::class,
        'results' => SubjectResultsRelationManager::class,
        'fees' => FeeVouchersRelationManager::class,
        'discipline' => DisciplineRecordsRelationManager::class,
        'messages' => ParentMessagesRelationManager::class,
    ];

    /**
     * The profile card under the header carries the student's name, so the page has no heading of its own.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            SendWhatsAppAction::make(),
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $ownerRecord = $this->getRecord();
        $livewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        return $schema->components([
            View::make('filament.resources.students.profile-header')
                ->viewData(['student' => $ownerRecord->loadMissing('latestAdmission.section', 'latestAdmission.campus', 'latestAdmission.year')]),
            Livewire::make(StudentOverview::class, ['record' => $ownerRecord])->key('overview'),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                $this->getInfolistContentComponent()->columnSpan(1),
                Livewire::make(CommentsRelationManager::class, [
                    ...$livewireData,
                    ...CommentsRelationManager::getDefaultProperties(),
                ])->key('comments')->columnSpan(1),
            ]),
            Tabs::make('studentRecord')
                ->tabs(collect(self::RECORD_TABS)
                    ->map(fn (string $relationManager, string $key): Tab => Tab::make($relationManager::getTitle($ownerRecord, static::class))
                        ->schema([
                            Livewire::make($relationManager, [
                                ...$livewireData,
                                ...$relationManager::getDefaultProperties(),
                            ])->key($key),
                        ])
                        ->visible(fn (): bool => $relationManager::canViewForRecord($ownerRecord, static::class)))
                    ->values()
                    ->all())
                ->persistTabInQueryString('record-tab'),
            Livewire::make(ContactsRelationManager::class, [
                ...$livewireData,
                ...ContactsRelationManager::getDefaultProperties(),
            ])->key('contacts'),
            Livewire::make(EnrollmentsRelationManager::class, [
                ...$livewireData,
                ...EnrollmentsRelationManager::getDefaultProperties(),
            ])->key('enrollments'),
        ]);
    }
}
