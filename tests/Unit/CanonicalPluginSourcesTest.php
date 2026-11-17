<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CanonicalPluginSourcesTest extends TestCase
{
    public function test_preflight_rejects_missing_or_mislabelled_plugin_sources(): void
    {
        $root = sys_get_temp_dir().'/canonical-source-'.bin2hex(random_bytes(8));
        mkdir($root.'/catalog/forms', 0755, true);
        file_put_contents($root.'/composer.json', json_encode(['repositories'=>[['type'=>'path','url'=>'catalog/forms','options'=>['versions'=>['pcteckserv/cms-contact-forms'=>'2.0.0']]]]]));
        $tool = dirname(__DIR__, 2).'/tools/validate-plugin-catalog.php';
        try {
            $this->assertSame(1, (new Process([PHP_BINARY, $tool, $root]))->run());
            file_put_contents($root.'/catalog/forms/cms-plugin.json', json_encode(['package'=>'pcteckserv/cms-contact-forms','version'=>'1.3.4']));
            $this->assertSame(1, (new Process([PHP_BINARY, $tool, $root]))->run());
            file_put_contents($root.'/catalog/forms/cms-plugin.json', json_encode(['package'=>'pcteckserv/cms-contact-forms','version'=>'2.0.0']));
            $this->assertSame(0, (new Process([PHP_BINARY, $tool, $root]))->run());
            mkdir($root.'/vendor');
            file_put_contents($root.'/vendor/autoload.php', '<?php');
            file_put_contents($root.'/catalog/forms/cms-plugin.json', '{}');
            $this->assertSame(0, (new Process([PHP_BINARY, $tool, $root]))->run());
        } finally { (new Filesystem)->deleteDirectory($root); }
    }
}
