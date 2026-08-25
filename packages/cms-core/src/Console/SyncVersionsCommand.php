<?php

namespace Pcteckserv\CmsCore\Console;

use Illuminate\Console\Command;
use Pcteckserv\CmsCore\Updates\PackageVersionRegistry;

class SyncVersionsCommand extends Command
{
    protected $signature = 'cms:sync-versions';

    protected $description = 'Sincroniza as versões instaladas dos packages CMS.';

    public function handle(PackageVersionRegistry $registry): int
    {
        $packages = $registry->sync();

        $this->table(
            ['Package', 'Versão instalada', 'Canal'],
            $packages->map(fn ($package): array => [
                $package->name,
                $package->installedVersion ?? '-',
                $package->channel,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
