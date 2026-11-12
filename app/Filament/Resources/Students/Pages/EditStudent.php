<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Illuminate\Database\Eloquent\Model;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;

        $data['firstname'] = $user?->firstname ?? '';
        $data['lastname'] = $user?->lastname ?? '';
        $data['email'] = $user?->email ?? '';

        return $data;
    }

    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getRelationManagers();
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        return Group::make(
            collect($managers)
                ->map(fn ($manager, $key): Livewire => Livewire::make(
                    $normalizedClass = $this->normalizeRelationManagerClass($manager),
                    [...$managerLivewireData, ...(($manager instanceof RelationManagerConfiguration) ? [...$manager->relationManager::getDefaultProperties(), ...$manager->getProperties()] : $manager::getDefaultProperties())],
                )->key("{$normalizedClass}-{$key}"))
                ->all()
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->user->update([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'] ?? null,
        ]);

        $record->update([
            'idnumber' => $data['idnumber'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'gender_id' => $data['gender_id'],
            'address' => $data['address'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'iban' => $data['iban'] ?? null,
            'bic' => $data['bic'] ?? null,
            'profession_id' => $data['profession_id'] ?? null,
            'institution_id' => $data['institution_id'] ?? null,
        ]);

        return $record;
    }
}
