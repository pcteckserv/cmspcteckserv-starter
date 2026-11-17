<?php

namespace Pcteckserv\Distribution;

use Pcteckserv\CmsCore\Updates\StarterUpdater;
use RuntimeException;

class CompatibleStarterUpdater extends StarterUpdater
{
    // Plugins opcionais do catálogo deste Starter. São geridos separadamente no
    // painel; copiar o site não deve instalar, remover ou reverter estes pacotes.
    private const OPTIONAL_PLUGINS = [
        'pcteckserv/cms-contact-forms', 'pcteckserv/cms-portfolio',
        'pcteckserv/cms-customers', 'pcteckserv/cms-agenda',
        'pcteckserv/cms-realstate', 'pcteckserv/cms-blog',
        'pcteckserv/cms-moloni', 'pcteckserv/cms-easypay',
        'pcteckserv/cms-paypal', 'pcteckserv/cms-shop',
        'pcteckserv/cms-ai', 'pcteckserv/cms-product-customization',
    ];

    protected function copyStarterFiles(string $source, string $destination): void
    {
        $read = static fn (string $path): array => is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $current = $read($destination.'/composer.json');
        $incoming = $read($source.'/composer.json');
        if (!is_string($current['name'] ?? null) || ($current['name'] ?? '') === '' || ($incoming['name'] ?? null) !== $current['name']) {
            throw new RuntimeException('O repositório configurado não contém o Starter deste site. A atualização foi interrompida antes de copiar ficheiros. Verifique STARTER_GITHUB_REPOSITORY.');
        }
        $installed = $read($destination.'/vendor/composer/installed.json');
        $locked = $read($source.'/composer.lock');
        $coreVersion = static function (array $packages): ?string {
            foreach ($packages as $package) if (($package['name'] ?? null) === 'pcteckserv/cms-core') return ltrim((string) $package['version'], 'v');
            return null;
        };
        $currentCore = $coreVersion($installed['packages'] ?? $installed);
        $targetCore = $coreVersion($locked['packages'] ?? []);
        if ($currentCore === null || $targetCore === null || explode('.', $currentCore)[0] !== explode('.', $targetCore)[0] || version_compare($currentCore, $targetCore, '<')) {
            throw new RuntimeException('Esta atualização do Starter não corresponde à versão instalada do Core. Nenhum ficheiro foi copiado; valide a compatibilidade antes de prosseguir.');
        }
        foreach (['require', 'require-dev', 'autoload', 'autoload-dev'] as $key) {
            $before = $current[$key] ?? [];
            $after = $incoming[$key] ?? [];
            unset($before['pcteckserv/cms-core'], $after['pcteckserv/cms-core']);
            if ($key === 'require') {
                foreach (self::OPTIONAL_PLUGINS as $package) {
                    unset($before[$package], $after[$package]);
                }
            }
            $before = $this->normaliseManifest($before);
            $after = $this->normaliseManifest($after);
            if ($before !== $after) throw new RuntimeException('Este Starter altera '.$key.' fora dos plugins opcionais e exige uma publicação preparada separadamente. Nenhum ficheiro foi copiado.');
        }
        // Uma atualização do Starter não deve repor o lock antigo depois de atualizar o Core.
        $preserved = [];
        try {
            foreach (['composer.json', 'composer.lock'] as $file) {
                if (is_file($source.'/'.$file)) {
                    $preserved[$file] = file_get_contents($source.'/'.$file);
                    if (!unlink($source.'/'.$file)) throw new RuntimeException('Não foi possível preservar as dependências do site.');
                }
            }
            parent::copyStarterFiles($source, $destination);
        } finally {
            foreach ($preserved as $file => $bytes) file_put_contents($source.'/'.$file, $bytes);
        }
    }

    private function normaliseManifest(array $values): array
    {
        if (! array_is_list($values)) ksort($values);
        foreach ($values as &$value) {
            if (is_array($value)) $value = $this->normaliseManifest($value);
        }

        return $values;
    }
}
