<?php

namespace App\Actions\Menu;

use App\Exceptions\BusinessException;
use App\Models\Menu;

class DeleteMenuAction
{
    public function execute(Menu $menu): void
    {
        if ($menu->is_system) {
            throw new BusinessException("\"{$menu->title}\" is a built-in menu and cannot be deleted. You can rename or move it instead.");
        }

        $childrenCount = $menu->children()->count();

        // Lapis kedua: foreign key RESTRICT di database.
        if ($childrenCount > 0) {
            throw new BusinessException("\"{$menu->title}\" still has {$childrenCount} submenu(s). Delete or move them first.");
        }

        $menu->delete();
    }
}
