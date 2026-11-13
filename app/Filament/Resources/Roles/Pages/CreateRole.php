<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\Pages\Concerns\SetsAllLevels;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    use SetsAllLevels;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->setAllLevelsAction()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $role = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);

        return RoleResource::saveAccess($role, $data);
    }

    protected function getRedirectUrl(): string
    {
        return RoleResource::getUrl();
    }
}
