<?php

namespace Tests\Unit;

use App\Services\Deployment\DatabaseSnapshot;
use Illuminate\Encryption\Encrypter;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DeploymentDatabaseSnapshotTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/deployment-snapshot-'.bin2hex(random_bytes(8)).'.enc';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function test_restaura_estrutura_opcoes_ids_binarios_e_valores_encriptados(): void
    {
        $source = $this->source();
        $key = random_bytes(32);
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        $source->prepare('INSERT INTO options VALUES (?, ?, ?)')->execute([42, 'opção com acentuação', $encrypter->encryptString('valor de teste')]);
        $source->exec('CREATE INDEX options_label_index ON options(label)');
        $snapshot = new DatabaseSnapshot;
        $snapshot->export($source, $this->path, 'palavra-passe-de-teste', 'base64:'.base64_encode($key));

        $destination = new PDO('sqlite::memory:');
        $header = $snapshot->restore($destination, $this->path, 'palavra-passe-de-teste');
        $row = $destination->query('SELECT * FROM options WHERE id = 42')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('opção com acentuação', $row['label']);
        $this->assertSame('valor de teste', (new Encrypter(base64_decode(substr($header['key'], 7)), 'AES-256-CBC'))->decryptString($row['value']));
        $this->assertSame("\0\xff\x80", $destination->query('SELECT value FROM options WHERE id = 1')->fetchColumn());
        $this->assertSame(1, (int) $destination->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'options_label_index'")->fetchColumn());
        $this->assertStringNotContainsString($header['key'], file_get_contents($this->path));
        $this->assertStringNotContainsString('opção', file_get_contents($this->path));
        $this->assertSame(0, (int) $destination->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
    }

    public function test_palavra_passe_errada_nao_altera_o_destino(): void
    {
        (new DatabaseSnapshot)->export($this->source(), $this->path, 'palavra-passe-de-teste', 'chave-de-teste');
        $this->assertRejectedWithoutChanges('palavra-passe-errada');
    }

    public function test_exporta_e_restaura_com_palavra_passe_curta(): void
    {
        $snapshot = new DatabaseSnapshot;
        $snapshot->export($this->source(), $this->path, '1', 'chave-de-teste');
        $destination = new PDO('sqlite::memory:');

        $header = $snapshot->restore($destination, $this->path, '1');

        $this->assertSame('chave-de-teste', $header['key']);
        $this->assertSame('origem', $destination->query('SELECT label FROM options WHERE id = 1')->fetchColumn());
        $this->assertStringNotContainsString('origem', file_get_contents($this->path));
    }

    public function test_exportacao_recusa_palavra_passe_vazia(): void
    {
        $this->expectException(RuntimeException::class);
        (new DatabaseSnapshot)->export($this->source(), $this->path, '', 'chave-de-teste');
    }

    public function test_copia_truncada_nao_altera_o_destino(): void
    {
        (new DatabaseSnapshot)->export($this->source(), $this->path, 'palavra-passe-de-teste', 'chave-de-teste');
        $lines = file($this->path);
        array_pop($lines);
        file_put_contents($this->path, implode('', $lines));
        $this->assertRejectedWithoutChanges('palavra-passe-de-teste');
    }

    public function test_registos_reordenados_nao_alteram_o_destino(): void
    {
        (new DatabaseSnapshot)->export($this->source(), $this->path, 'palavra-passe-de-teste', 'chave-de-teste');
        $lines = file($this->path);
        [$lines[2], $lines[3]] = [$lines[3], $lines[2]];
        file_put_contents($this->path, implode('', $lines));
        $this->assertRejectedWithoutChanges('palavra-passe-de-teste');
    }

    public function test_exige_confirmacao_para_substituir_e_restaura_quando_confirmado(): void
    {
        $snapshot = new DatabaseSnapshot;
        $snapshot->export($this->source(), $this->path, 'palavra-passe-de-teste', 'chave-de-teste');
        $destination = $this->source();
        $destination->exec("UPDATE options SET label = 'destino'");
        try {
            $snapshot->restore($destination, $this->path, 'palavra-passe-de-teste');
            $this->fail('A substituição sem confirmação deve ser recusada.');
        } catch (RuntimeException) {
            $this->assertSame('destino', $destination->query('SELECT label FROM options')->fetchColumn());
        }
        $snapshot->restore($destination, $this->path, 'palavra-passe-de-teste', true);
        $this->assertSame('origem', $destination->query('SELECT label FROM options')->fetchColumn());
    }

    private function assertRejectedWithoutChanges(string $password): void
    {
        $destination = $this->source();
        $destination->exec("UPDATE options SET label = 'destino'");
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, $password, true);
            $this->fail('Uma cópia inválida deve ser recusada.');
        } catch (RuntimeException) {
            $this->assertSame('destino', $destination->query('SELECT label FROM options')->fetchColumn());
        }
    }

    private function source(): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE options (id INTEGER PRIMARY KEY, label TEXT NOT NULL, value BLOB)');
        $database->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $database->exec("INSERT INTO sessions VALUES ('sessao-de-teste')");
        $database->prepare('INSERT INTO options VALUES (1, ?, ?)')->execute(['origem', "\0\xff\x80"]);

        return $database;
    }
}
