<?php

namespace Pcteckserv\CmsCore\Console;

use Illuminate\Console\Command;
use Pcteckserv\CmsCore\Services\PermissionSynchronizer;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'cms:permissions-sync';

    protected $description = 'Sincroniza permissões registadas pelo Core e por plugins com a base de dados.';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $count = $synchronizer->sync();

        $this->info("Permissões sincronizadas: {$count}");

        return self::SUCCESS;
    }
}
