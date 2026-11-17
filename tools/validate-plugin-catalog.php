<?php
// Executado antes do Composer; não precisa de vendor nem altera ficheiros.
$root = $argv[1] ?? dirname(__DIR__);
// A aplicação instalada usa o atualizador do Core, que valida a origem efetiva.
// Não bloquear atualizações por causa de repositórios antigos já ultrapassados.
if (is_file($root.'/vendor/autoload.php')) exit(0);
try {
    $composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($composer['repositories'] ?? [] as $repository) {
        if (($repository['type'] ?? null) !== 'path' || !isset($repository['options']['versions']['pcteckserv/cms-contact-forms'])) continue;
        $path = $root.'/'.$repository['url'];
        $manifest = is_file($path.'/cms-plugin.json') ? json_decode(file_get_contents($path.'/cms-plugin.json'), true, flags: JSON_THROW_ON_ERROR) : [];
        if (($manifest['package'] ?? null) !== 'pcteckserv/cms-contact-forms'
            || ($manifest['version'] ?? null) !== $repository['options']['versions']['pcteckserv/cms-contact-forms']) {
            throw new RuntimeException('Prepare o catálogo PCTECKSERV indicado no README. A versão real de Formulários tem de corresponder à versão declarada; não é seguro instalar outra versão sob os mesmos metadados.');
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "O catálogo de plugins não corresponde à base preparada. Consulte README.md antes de instalar ou atualizar dependências.\n");
    exit(1);
}
