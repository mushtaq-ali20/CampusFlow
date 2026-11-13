<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The rules of the users and their roles: one role per user, the administrator works across all branches, and the
 * school always keeps at least one active administrator. Nobody locks themselves out.
 */
class UserAccessService
{
    /**
     * Creates or updates a user: their details, role, branch and whether they may sign in. A password set here is
     * temporary: the person must choose their own at the next sign-in.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function save(User $user, array $data, User $by): User
    {
        $role = $data['role'];
        $active = (bool) ($data['is_active'] ?? true);
        $password = $data['password'] ?? null;
        unset($data['role'], $data['is_active'], $data['password']);

        if ($role === AccessModules::ADMINISTRATOR && ! $by->isAdministrator()) {
            throw new DomainException(__('Only an administrator can make someone an administrator.'));
        }

        if ($user->exists) {
            if ($user->is($by) && ($role !== $user->roles->first()?->name || ! $active)) {
                throw new DomainException(__('You cannot change your own role or deactivate yourself.'));
            }

            if ($user->isAdministrator() && ($role !== AccessModules::ADMINISTRATOR || ! $active)) {
                $this->assertAnotherAdministrator($user);
            }
        }

        if ($role === AccessModules::ADMINISTRATOR) {
            $data['campus_id'] = null;
        }

        return DB::transaction(function () use ($user, $data, $role, $active, $password): User {
            if (filled($password)) {
                $data['password'] = Hash::make($password);
            }

            $user->fill($data)->save();
            $user->syncRoles([$role]);
            $user->accessState()->updateOrCreate([], [
                'is_active' => $active,
                ...(filled($password) ? ['must_change_password' => true] : []),
            ]);

            return $user->refresh();
        });
    }

    /**
     * @throws DomainException
     */
    public function deactivate(User $user, User $by): User
    {
        if ($user->is($by)) {
            throw new DomainException(__('You cannot deactivate yourself.'));
        }

        if ($user->isAdministrator()) {
            $this->assertAnotherAdministrator($user);
        }

        $user->accessState()->updateOrCreate([], ['is_active' => false]);

        return $user->refresh();
    }

    public function activate(User $user): User
    {
        $user->accessState()->updateOrCreate([], ['is_active' => true]);

        return $user->refresh();
    }

    /**
     * The person sets their own password, which ends the request to change it.
     */
    public function changeOwnPassword(User $user, string $password): User
    {
        $user->forceFill(['password' => Hash::make($password)])->save();
        $user->accessState()->updateOrCreate([], ['must_change_password' => false]);

        return $user->refresh();
    }

    /**
     * @throws DomainException
     */
    private function assertAnotherAdministrator(User $user): void
    {
        $others = User::query()
            ->role(AccessModules::ADMINISTRATOR)
            ->whereKeyNot($user->getKey())
            ->whereDoesntHave('accessState', fn ($state) => $state->where('is_active', false))
            ->exists();

        if (! $others) {
            throw new DomainException(__('The school must keep at least one active administrator.'));
        }
    }
}
