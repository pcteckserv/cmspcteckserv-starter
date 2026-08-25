<?php

namespace Pcteckserv\CmsCore\Support;

use Illuminate\Support\Facades\Schema;
use Pcteckserv\CmsCore\Models\SiteOption;
use Throwable;

class SiteOptions
{
    public function all(): array
    {
        $defaults = $this->defaults();

        try {
            if (! Schema::hasTable('cms_site_options')) {
                return $defaults;
            }
        } catch (Throwable) {
            return $defaults;
        }

        $stored = SiteOption::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->all();

        return array_replace($defaults, array_filter(
            $stored,
            static fn ($value): bool => $value !== null && $value !== ''
        ));
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->all()[$key] ?? $fallback;
    }

    public function setMany(array $options): void
    {
        foreach ($options as $key => $value) {
            SiteOption::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    public function applyMailConfig(): void
    {
        $options = $this->all();

        if (! $this->truthy($options['smtp_enabled'] ?? false)) {
            return;
        }

        $encryption = $options['smtp_encryption'] ?: null;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.host' => $options['smtp_host'],
            'mail.mailers.smtp.port' => (int) $options['smtp_port'],
            'mail.mailers.smtp.username' => $options['smtp_username'] ?: null,
            'mail.mailers.smtp.password' => $options['smtp_password'] ?: null,
            'mail.from.address' => $options['smtp_from_address'],
            'mail.from.name' => $options['smtp_from_name'],
        ]);
    }

    public function defaults(): array
    {
        return config('cms-core.site_options', []);
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
