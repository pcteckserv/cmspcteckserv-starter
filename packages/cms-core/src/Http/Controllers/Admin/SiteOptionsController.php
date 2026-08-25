<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Pcteckserv\CmsCore\Support\SiteOptions;

class SiteOptionsController extends Controller
{
    public function edit(SiteOptions $siteOptions): View
    {
        return view('cms-core::admin.site-options.edit', [
            'options' => $siteOptions->all(),
            'locales' => $this->locales(),
        ]);
    }

    public function update(Request $request, SiteOptions $siteOptions): RedirectResponse
    {
        $validated = $request->validate([
            'site_title' => ['required', 'string', 'max:120'],
            'site_description' => ['nullable', 'string', 'max:255'],
            'site_icon_url' => ['nullable', 'string', 'max:2048'],
            'site_icon_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:2048'],
            'remove_site_icon' => ['nullable', 'boolean'],
            'site_url' => ['required', 'url', 'max:2048'],
            'admin_email' => ['required', 'email', 'max:255'],
            'locale' => ['required', Rule::in(array_keys($this->locales()))],
        ]);

        if ($request->boolean('remove_site_icon')) {
            $this->deleteStoredSiteIcon($validated['site_icon_url'] ?? null);
            $validated['site_icon_url'] = '';
        } elseif ($request->hasFile('site_icon_file')) {
            $validated['site_icon_url'] = '/storage/'.$request->file('site_icon_file')->store('cms/site-icons', 'public');
        }

        unset($validated['site_icon_file'], $validated['remove_site_icon']);

        $siteOptions->setMany($validated);
        $this->syncAppUrlEnv($validated['site_url']);

        return back()->with('cms_site_options_success', 'Opções gerais guardadas com sucesso.');
    }

    private function locales(): array
    {
        return [
            'pt_PT' => 'Português',
            'en_US' => 'Inglês',
            'es_ES' => 'Espanhol',
            'fr_FR' => 'Francês',
        ];
    }

    private function deleteStoredSiteIcon(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! Str::startsWith($path, '/storage/cms/site-icons/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($path, '/storage/'));
    }

    private function syncAppUrlEnv(string $url): void
    {
        config(['app.url' => $url]);

        $envPath = base_path('.env');
        $envValue = $this->formatEnvValue($url);

        if (! file_exists($envPath)) {
            file_put_contents($envPath, "APP_URL={$envValue}".PHP_EOL);

            return;
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            return;
        }

        if (preg_match('/^APP_URL=/m', $contents) === 1) {
            $contents = preg_replace('/^APP_URL=.*/m', "APP_URL={$envValue}", $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL."APP_URL={$envValue}".PHP_EOL;
        }

        file_put_contents($envPath, $contents);
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"/', $value) !== 1) {
            return $value;
        }

        return '"'.str_replace('"', '\"', $value).'"';
    }
}
