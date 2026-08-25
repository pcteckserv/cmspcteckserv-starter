<?php

namespace Pcteckserv\CmsCore\Updates;

final readonly class UpdateResult
{
    public function __construct(
        public bool $successful,
        public string $message,
    ) {
    }
}
