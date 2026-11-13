<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\Pages\Concerns\SetsAllLevels;
use App\Filament\Resources\Roles\RoleResource;
use App\Services\AccessModules;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    use SetsAllLevels;

    protected static string $resource = RoleResource::class;

    public function getSubheading(): ?string
    {
        $count = $this->getRecord()->users()->count();

        return trans_choice('{0} Nobody has this role yet.|{1} :count user has this role: saving changes their access at once.|[2,*] :count users have this role: saving changes their access at once.', $count, ['count' => $count]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->setAllLevelsAction()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();

        return [...$data, ...RoleResource::accessData($role)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        if (isset($data['name']) && ! in_array($record->name, AccessModules::SYSTEM_ROLES, true)) {
            $record->update(['name' => trim($data['name'])]);
        }

        return RoleResource::saveAccess($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl();
    }
}
