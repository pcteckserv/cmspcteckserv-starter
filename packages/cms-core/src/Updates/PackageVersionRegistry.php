<?php

namespace Pcteckserv\CmsCore\Updates;

use Composer\InstalledVersions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackageVersionRegistry
{
    public function __construct(
        private readonly GitTagUpdateChecker $updateChecker,
    ) {
    }

    /**
     * @return Collection<int, InstalledPackage>
     */
    public function sync(): Collection
    {
        $packages = collect(config('cms-core.updates.packages', []))
            ->filter(fn (mixed $package): bool => is_string($package) && $package !== '')
            ->unique()
            ->values();

        $channel = config('cms-core.updates.channel', 'stable');

        return $packages->map(function (string $package) use ($channel): InstalledPackage {
            $installedVersion = $this->installedVersion($package);

            DB::table('cms_installed_packages')->updateOrInsert(
                ['name' => $package],
                [
                    'installed_version' => $installedVersion,
                    'channel' => $channel,
                    'checked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return new InstalledPackage($package, $installedVersion, null, $channel, now()->toDateTimeString());
        });
    }

    /**
     * @return Collection<int, InstalledPackage>
     */
    public function checkRemoteUpdates(): Collection
    {
        $this->sync();

        $packages = collect(config('cms-core.updates.packages', []))
            ->filter(fn (mixed $package): bool => is_string($package) && $package !== '')
            ->unique()
            ->values();

        foreach ($packages as $package) {
            $availableVersion = $this->updateChecker->latestVersion($package);

            if ($availableVersion === null) {
                DB::table('cms_installed_packages')
                    ->where('name', $package)
                    ->update([
                        'checked_at' => now(),
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('cms_installed_packages')
                ->where('name', $package)
                ->update([
                    'available_version' => $availableVersion,
                    'checked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $this->all();
    }

    /**
     * @return Collection<int, InstalledPackage>
     */
    public function all(): Collection
    {
        return DB::table('cms_installed_packages')
            ->orderBy('name')
            ->get()
            ->map(fn (object $package): InstalledPackage => new InstalledPackage(
                $package->name,
                $package->installed_version,
                $package->available_version,
                $package->channel,
                $package->checked_at,
            ));
    }

    private function installedVersion(string $package): ?string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package) ?: InstalledVersions::getVersion($package);
    }
}
