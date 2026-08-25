<?php

namespace Pcteckserv\CmsCore\Support\Permissions;

use InvalidArgumentException;

class PermissionRegistry
{
    /** @var array<string, PermissionDefinition> */
    private array $permissions = [];

    /**
     * @param  array<string, array{label:string, group:string, description?:string|null}|string>  $permissions
     */
    public function register(array $permissions, string $defaultGroup = 'Core'): void
    {
        foreach ($permissions as $key => $definition) {
            if (is_string($definition)) {
                $this->registerOne(new PermissionDefinition($key, $definition, $defaultGroup));

                continue;
            }

            $this->registerOne(new PermissionDefinition(
                key: $key,
                label: $definition['label'],
                group: $definition['group'] ?? $defaultGroup,
                description: $definition['description'] ?? null,
            ));
        }
    }

    public function registerOne(PermissionDefinition $permission): void
    {
        if (! preg_match('/^[a-z0-9]+(?:\.[a-z0-9_-]+)+$/', $permission->key)) {
            throw new InvalidArgumentException("A permissão [{$permission->key}] deve seguir a convenção <namespace>.<recurso>.<ação>.");
        }

        $this->permissions[$permission->key] = $permission;
    }

    /**
     * @param  iterable<PermissionDefinition>  $permissions
     */
    public function registerMany(iterable $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->registerOne($permission);
        }
    }

    /** @return array<string, PermissionDefinition> */
    public function all(): array
    {
        ksort($this->permissions);

        return $this->permissions;
    }

    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }
}
