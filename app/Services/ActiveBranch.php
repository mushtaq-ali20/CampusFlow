<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\User;

/**
 * The branch the signed-in user is working on, chosen with the branch switcher of the top bar. Staff attached to
 * a branch always work on their branch. For the others, no branch (null) means all branches, which is the
 * default after signing in. Branch-aware queries (see BelongsToBranch) follow it.
 */
class ActiveBranch
{
    public const SESSION_KEY = 'active_branch_id';

    private ?Campus $resolved = null;

    private bool $isResolved = false;

    /**
     * The id of the active branch, or null for all branches.
     */
    public function id(): ?int
    {
        return $this->campus()?->id;
    }

    /**
     * The active branch, or null for all branches.
     */
    public function campus(): ?Campus
    {
        if (! $this->isResolved) {
            $branchId = $this->user()?->campus_id ?? session(self::SESSION_KEY);
            $this->resolved = $branchId === null ? null : Campus::query()->find($branchId);
            $this->isResolved = true;
        }

        return $this->resolved;
    }

    /**
     * Whether the user is attached to a branch, and so cannot switch to another branch.
     */
    public function isLocked(): bool
    {
        return $this->user()?->campus_id !== null;
    }

    /**
     * Works on a branch, or on all branches with null. Staff attached to a branch cannot switch.
     */
    public function set(?int $branchId): void
    {
        if ($this->isLocked()) {
            return;
        }

        if ($branchId === null || Campus::query()->whereKey($branchId)->doesntExist()) {
            session()->forget(self::SESSION_KEY);
        } else {
            session()->put(self::SESSION_KEY, $branchId);
        }

        $this->forgetResolved();
    }

    /**
     * Goes back to all branches, as after signing in.
     */
    public function reset(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->forgetResolved();
    }

    public function forgetResolved(): void
    {
        $this->resolved = null;
        $this->isResolved = false;
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
