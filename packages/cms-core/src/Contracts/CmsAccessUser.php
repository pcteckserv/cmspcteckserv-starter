<?php

namespace Pcteckserv\CmsCore\Contracts;

interface CmsAccessUser
{
    public function hasCmsPermission(string $permission): bool;

    public function hasCmsRole(string $role): bool;

    public function isCmsSuperAdmin(): bool;
}
