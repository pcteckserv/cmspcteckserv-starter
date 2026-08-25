<?php

namespace Pcteckserv\CmsCore\Services;

use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Support\Permissions\PermissionRegistry;

class PermissionSynchronizer
{
    public function __construct(private readonly PermissionRegistry $registry)
    {
    }

    public function sync(): int
    {
        $count = 0;

        foreach ($this->registry->all() as $definition) {
            Permission::query()->updateOrCreate(
                ['key' => $definition->key],
                [
                    'label' => $definition->label,
                    'group' => $definition->group,
                    'description' => $definition->description,
                ],
            );

            $count++;
        }

        return $count;
    }
}
