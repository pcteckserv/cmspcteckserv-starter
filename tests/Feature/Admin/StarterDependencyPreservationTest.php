<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\File;
use Pcteckserv\Distribution\CompatibleStarterUpdater;
use Tests\TestCase;

class StarterDependencyPreservationTest extends TestCase
{
    public function test_starter_accepts_optional_plugin_changes_without_replacing_installed_code_or_composer(): void
    {
        $this->assertInstanceOf(CompatibleStarterUpdater::class, app(\Pcteckserv\CmsCore\Updates\StarterUpdater::class));
        $root = sys_get_temp_dir().'/starter-preserve-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root.'/source');
        File::ensureDirectoryExists($root.'/target/vendor/composer');
        $current = ['name'=>'pcteckserv/example','require'=>['php'=>'^8.3','pcteckserv/cms-core'=>'^3.1.2','pcteckserv/cms-customers'=>'^1.3']];
        $incoming = $current;
        $incoming['require']['pcteckserv/cms-customers'] = '^1.5';
        $incoming['require']['pcteckserv/cms-product-customization'] = '^0.3';
        File::put($root.'/source/composer.json', $source = json_encode($incoming));
        File::put($root.'/source/composer.lock', '{"packages":[{"name":"pcteckserv/cms-core","version":"3.1.2"}]}');
        File::put($root.'/source/updated.txt', 'new site');
        File::put($root.'/target/composer.json', $before = json_encode($current));
        File::put($root.'/target/composer.lock', 'original lock');
        File::put($root.'/target/vendor/composer/installed.json', '{"packages":[{"name":"pcteckserv/cms-core","version":"3.1.12"}]}');
        File::put($root.'/target/vendor/installed-data.txt', 'preserved');
        $updater = new class extends CompatibleStarterUpdater { public function copy($source, $target): void { $this->copyStarterFiles($source, $target); } };
        try {
            $updater->copy($root.'/source', $root.'/target');
            $this->assertSame($before, File::get($root.'/target/composer.json'));
            $this->assertSame('original lock', File::get($root.'/target/composer.lock'));
            $this->assertSame('preserved', File::get($root.'/target/vendor/installed-data.txt'));
            $this->assertSame('new site', File::get($root.'/target/updated.txt'));
            $this->assertSame($source, File::get($root.'/source/composer.json'));
        } finally { File::deleteDirectory($root); }
    }

    public function test_starter_refuses_other_projects_new_libraries_autoload_and_other_core_lines(): void
    {
        $root = sys_get_temp_dir().'/starter-reject-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($root.'/source');
        File::ensureDirectoryExists($root.'/target/vendor/composer');
        $manifest = ['name'=>'pcteckserv/example','require'=>['php'=>'^8.3']];
        File::put($root.'/target/composer.json', $before = json_encode($manifest));
        File::put($root.'/target/vendor/composer/installed.json', '{"packages":[{"name":"pcteckserv/cms-core","version":"3.1.12"}]}');
        File::put($root.'/source/updated.txt', 'must not copy');
        $updater = new class extends CompatibleStarterUpdater { public function copy($source, $target): void { $this->copyStarterFiles($source, $target); } };
        try {
            foreach ([['name'=>'pcteckserv/foreign'], ['require'=>['php'=>'^8.3','example/required'=>'^1.0']], ['autoload'=>['files'=>['new.php']]], []] as $index => $change) {
                File::put($root.'/source/composer.json', json_encode(array_replace($manifest, $change)));
                File::put($root.'/source/composer.lock', json_encode(['packages'=>[['name'=>'pcteckserv/cms-core','version'=>$index === 3 ? '4.0.0' : '3.1.2']]]));
                try { $updater->copy($root.'/source', $root.'/target'); $this->fail('A cópia devia ser recusada.'); }
                catch (\RuntimeException) { $this->assertFileDoesNotExist($root.'/target/updated.txt'); }
                $this->assertSame($before, File::get($root.'/target/composer.json'));
            }
        } finally { File::deleteDirectory($root); }
    }
}
