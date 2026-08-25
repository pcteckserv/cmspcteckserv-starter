<?php

namespace Pcteckserv\CmsCore\Support\Permissions;

final readonly class PermissionDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public ?string $description = null,
    ) {
    }
}
