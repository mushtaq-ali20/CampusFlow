<?php

namespace App\Filament\Resources\Roles\Pages\Concerns;

use App\Services\AccessModules;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The "Set all modules to" buttons of the role form.
 */
trait SetsAllLevels
{
    protected function setAllLevelsAction(): ActionGroup
    {
        return ActionGroup::make([
            $this->setAllTo('allNone', __('No access'), AccessModules::NONE),
            $this->setAllTo('allRead', __('Read only'), AccessModules::READ),
            $this->setAllTo('allFull', __('Full access'), AccessModules::FULL),
        ])
            ->label(__('Set all modules to'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->button()
            ->color('gray');
    }

    private function setAllTo(string $name, string $label, string $level): Action
    {
        return Action::make($name)
            ->label($label)
            ->action(function () use ($level): void {
                foreach (array_keys(AccessModules::all()) as $module) {
                    $options = AccessModules::levelOptions($module);
                    $this->data['levels'][$module] = array_key_exists($level, $options) ? $level : AccessModules::FULL;
                }
            });
    }
}
