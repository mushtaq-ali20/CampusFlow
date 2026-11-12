<?php

namespace App\Livewire;

use App\Models\Campus;
use App\Services\ActiveBranch;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The branch switcher of the top bar, beside the notifications: "All Branches" or one branch. The choice filters
 * every branch-aware screen (see ActiveBranch), and the page reloads to show the data of the chosen branch.
 * Staff attached to a branch see their branch, locked.
 */
class BranchSwitcher extends Component
{
    #[Locked]
    public string $returnUrl = '';

    public function mount(): void
    {
        $url = request()->fullUrl();

        $this->returnUrl = Str::contains($url, '/livewire') ? '' : $url;
    }

    public function switchTo(?int $branchId = null): void
    {
        app(ActiveBranch::class)->set($branchId);

        $this->redirect(filled($this->returnUrl) ? $this->returnUrl : Filament::getUrl());
    }

    public function render(): View
    {
        $activeBranch = app(ActiveBranch::class);

        return view('livewire.branch-switcher', [
            'branches' => Campus::query()->orderBy('id')->get(),
            'active' => $activeBranch->campus(),
            'isLocked' => $activeBranch->isLocked(),
        ]);
    }
}
