<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Records of a branch (campus_id column). Staff attached to a branch only see the records of their branch, and
 * the others see the records of the branch chosen in the branch switcher, or of all branches.
 */
trait BelongsToBranch
{
    /**
     * Only the records of the branch the user works on (see User::activeBranchId()), or all of them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $branchId = $user?->activeBranchId();

        if ($branchId !== null) {
            $query->where($this->qualifyColumn('campus_id'), $branchId);
        }
    }
}
