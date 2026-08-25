<?php

namespace Pcteckserv\CmsCore\Console;

use Illuminate\Console\Command;
use Pcteckserv\CmsCore\Updates\PackageVersionRegistry;

class CheckUpdatesCommand extends Command
{
    protected $signature = 'cms:check-updates';

    protected $description = 'Mostra as versões dos packages CMS instalados.';

    public function handle(PackageVersionRegistry $registry): int
    {
        if (! config('cms-core.updates.enabled', true)) {
            $this->warn('O sistema de atualizações do CMS está desativado.');

            return self::SUCCESS;
        }

        $packages = $registry->checkRemoteUpdates();

        if ($packages->isEmpty()) {
            $this->info('Ainda não há packages CMS registados. Execute php artisan cms:sync-versions.');

            return self::SUCCESS;
        }

        $this->table(
            ['Package', 'Instalada', 'Disponível', 'Canal', 'Estado'],
            $packages->map(fn ($package): array => [
                $package->name,
                $package->installedVersion ?? '-',
                $package->availableVersion ?? '-',
                $package->channel,
                $package->hasUpdate() ? 'update disponível' : 'ok',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
