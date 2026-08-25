<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Pcteckserv\CmsCore\Updates\PackageVersionRegistry;
use Pcteckserv\CmsCore\Updates\PackageUpdater;

class UpdatesController extends Controller
{
    public function index(PackageVersionRegistry $registry): View
    {
        $packages = config('cms-core.updates.enabled', true)
            ? $registry->checkRemoteUpdates()
            : $registry->all();

        return view('cms-core::admin.updates.index', [
            'packages' => $packages,
            'updatesEnabled' => config('cms-core.updates.enabled', true),
            'channel' => config('cms-core.updates.channel', 'stable'),
        ]);
    }

    public function update(string $package, PackageUpdater $updater, PackageVersionRegistry $registry): RedirectResponse
    {
        if (! config('cms-core.updates.enabled', true)) {
            return redirect()
                ->route('admin.updates.index')
                ->with('cms_update_error', 'O sistema de atualizações está desativado.');
        }

        if (! in_array($package, config('cms-core.updates.packages', []), true)) {
            return redirect()
                ->route('admin.updates.index')
                ->with('cms_update_error', 'Package CMS inválido.');
        }

        $result = $updater->update($package);
        $registry->checkRemoteUpdates();

        return redirect()
            ->route('admin.updates.index')
            ->with(
                $result->successful ? 'cms_update_success' : 'cms_update_error',
                $result->message,
            );
    }
}
