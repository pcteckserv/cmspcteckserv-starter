<?php

namespace Tests\Unit;

use App\Services\Deployment\DatabaseSnapshot;
use Illuminate\Encryption\Encrypter;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\UsesDeploymentMysql;

class DeploymentSqliteMysqlMigrationTest extends TestCase
{
    use UsesDeploymentMysql;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/sqlite-mysql-'.bin2hex(random_bytes(8)).'.enc';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        $this->dropMysqlDatabase();
        parent::tearDown();
    }

    public function test_migra_ids_relacoes_unicode_binarios_encriptacao_e_preserva_estrutura_mysql(): void
    {
        $destination = $this->destination();
        $source = $this->source();
        $source->exec("INSERT INTO users VALUES (0, 'zero@example.test')");
        $key = random_bytes(32);
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        $source->prepare('INSERT INTO options VALUES (42, 7, ?, ?, ?)')->execute(['opção çã 😀', "\0\xff\x80", $encrypter->encryptString('valor protegido')]);
        $snapshot = new DatabaseSnapshot;
        $snapshot->export($source, $this->path, 'palavra-passe-de-teste', 'base64:'.base64_encode($key));
        $originalMode = $destination->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $header = $snapshot->restore($destination, $this->path, 'palavra-passe-de-teste', true);
        $row = $destination->query('SELECT * FROM options WHERE id = 42')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('opção çã 😀', $row['label']);
        $this->assertSame("\0\xff\x80", $row['value']);
        $this->assertSame(7, (int) $row['user_id']);
        $this->assertSame('valor protegido', (new Encrypter(base64_decode(substr($header['key'], 7)), 'AES-256-CBC'))->decryptString($row['encrypted']));
        $this->assertSame('origem@example.test', $destination->query('SELECT email FROM users WHERE id = 7')->fetchColumn());
        $this->assertSame('zero@example.test', $destination->query('SELECT email FROM users WHERE id = 0')->fetchColumn());
        $this->assertSame('mysql_schema', $destination->query('SELECT migration FROM migrations')->fetchColumn());
        $this->assertSame(0, (int) $destination->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
        $this->assertSame(1, (int) $destination->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn());
        $this->assertSame($originalMode, $destination->query('SELECT @@SESSION.sql_mode')->fetchColumn());
        $destination->exec("INSERT INTO users (email) VALUES ('novo@example.test')");
        $this->assertGreaterThan(7, (int) $destination->lastInsertId());
        $this->expectException(PDOException::class);
        $destination->exec("INSERT INTO users (email) VALUES ('origem@example.test')");
    }

    public function test_recusa_substituicao_sem_confirmacao(): void
    {
        $destination = $this->destination();
        $this->export();
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste');
            $this->fail('A substituição exige confirmação.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Confirme explicitamente', $exception->getMessage());
        }
        $this->assertDestinationPreserved($destination);
    }

    public function test_migra_as_estruturas_reais_do_site_e_dos_plugins_para_mysql(): void
    {
        $database = $this->mysqlDatabase();
        $parts = [];
        parse_str(str_replace(';', '&', substr(getenv('DEPLOY_TEST_MYSQL_DSN'), 6)), $parts);
        $directory = dirname(__DIR__, 2);
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => $parts['host'] ?? '127.0.0.1',
            'DB_PORT' => $parts['port'] ?? '3306', 'DB_DATABASE' => $database->query('SELECT DATABASE()')->fetchColumn(),
            'DB_USERNAME' => getenv('DEPLOY_TEST_MYSQL_USER') ?: '', 'DB_PASSWORD' => getenv('DEPLOY_TEST_MYSQL_PASSWORD') ?: '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        $process = new Process([PHP_BINARY, $directory.'/artisan', 'migrate', '--force'], $directory, $environment);
        $process->setTimeout(90);
        $process->mustRun();

        $tables = (new DatabaseSnapshot)->tables($database);
        $this->assertContains('users', $tables);
        $this->assertContains('cms_plugins', $tables);
        $this->assertContains('cms_contact_forms', $tables);
        $this->assertGreaterThan(20, count($tables));

        $sqlitePath = $this->path.'.sqlite';
        touch($sqlitePath);
        try {
            $process = new Process([PHP_BINARY, $directory.'/artisan', 'migrate', '--force'], $directory, array_replace($environment, [
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $sqlitePath,
            ]));
            $process->setTimeout(90)->mustRun();
            $source = new PDO('sqlite:'.$sqlitePath);
            $snapshot = new DatabaseSnapshot;
            $snapshot->export($source, $this->path, 'palavra-passe-de-teste', $environment['APP_KEY']);
            $header = $snapshot->restore($database, $this->path, 'palavra-passe-de-teste', true);

            foreach (array_diff($header['tables'], ['migrations']) as $table) {
                $this->assertSame(
                    (int) $source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn(),
                    (int) $database->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn(),
                    'A tabela '.$table.' deve preservar os registos.',
                );
            }
        } finally {
            $source = null;
            unlink($sqlitePath);
        }
    }

    public function test_tabela_ou_coluna_em_falta_nao_altera_os_dados(): void
    {
        $destination = $this->destination();
        $source = $this->source();
        $source->exec('CREATE TABLE missing_table (id INTEGER PRIMARY KEY)');
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('As tabelas em falta devem ser recusadas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Faltam tabelas', $exception->getMessage());
        }
        $this->assertDestinationPreserved($destination);
        unlink($this->path);
        $source->exec('DROP TABLE missing_table');
        $source->exec('ALTER TABLE users ADD COLUMN missing_column TEXT');
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('As colunas em falta devem ser recusadas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Faltam colunas', $exception->getMessage());
        }
        $this->assertDestinationPreserved($destination);
    }

    public function test_relacoes_invalidas_fazem_rollback_e_repoem_a_ligacao(): void
    {
        $destination = $this->destination();
        $source = $this->source();
        $source->exec("INSERT INTO options VALUES (42, 999, 'órfão', NULL, NULL)");
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('As relações inválidas devem ser recusadas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('relações inválidas', $exception->getMessage());
        }
        $this->assertDestinationPreserved($destination);
        $this->assertSame(0, (int) $destination->query('SELECT COUNT(*) FROM options')->fetchColumn());
    }

    public function test_colisao_de_unicidade_no_mysql_faz_rollback(): void
    {
        $destination = $this->destination();
        $source = $this->source();
        $source->exec("INSERT INTO users VALUES (8, 'ORIGEM@example.test')");
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('A collation MySQL deve recusar valores duplicados.');
        } catch (PDOException) {
            $this->assertDestinationPreserved($destination);
        }
    }

    public function test_modo_estrito_impede_truncagem_silenciosa(): void
    {
        $destination = $this->destination();
        $destination->exec("SET SESSION sql_mode = ''");
        $source = $this->source();
        $source->prepare('UPDATE users SET email = ?')->execute([str_repeat('a', 300)]);
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('Os valores demasiado longos não devem ser truncados.');
        } catch (PDOException) {
            $this->assertDestinationPreserved($destination);
        }
        $this->assertSame('', $destination->query('SELECT @@SESSION.sql_mode')->fetchColumn());
    }

    public function test_palavra_passe_errada_nao_altera_mysql(): void
    {
        $destination = $this->destination();
        $this->export();
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'errada', true);
            $this->fail('A palavra-passe incorreta deve ser recusada.');
        } catch (RuntimeException) {
            $this->assertDestinationPreserved($destination);
        }
    }

    public function test_triggers_sqlite_nao_sao_ignorados_silenciosamente(): void
    {
        $destination = $this->destination();
        $source = $this->source();
        $source->exec("CREATE TRIGGER change_user AFTER INSERT ON users BEGIN UPDATE users SET email = 'trigger' WHERE id = NEW.id; END");
        $this->export($source);
        try {
            (new DatabaseSnapshot)->restore($destination, $this->path, 'palavra-passe-de-teste', true);
            $this->fail('Os triggers exigem adaptação para MySQL.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('triggers', $exception->getMessage());
        }
        $this->assertDestinationPreserved($destination);
    }

    private function export(?PDO $source = null): void
    {
        (new DatabaseSnapshot)->export($source ?? $this->source(), $this->path, 'palavra-passe-de-teste', 'chave-de-teste');
    }

    private function assertDestinationPreserved(PDO $database): void
    {
        $this->assertSame('destino@example.test', $database->query('SELECT email FROM users')->fetchColumn());
        $this->assertSame(1, (int) $database->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame(1, (int) $database->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn());
        $this->assertFalse($database->inTransaction());
    }

    private function source(): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE)');
        $database->exec('CREATE TABLE options (id INTEGER PRIMARY KEY, user_id INTEGER, label TEXT, value BLOB, encrypted TEXT, FOREIGN KEY (user_id) REFERENCES users(id))');
        $database->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $database->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration TEXT, batch INTEGER)');
        $database->exec("INSERT INTO users VALUES (7, 'origem@example.test')");
        $database->exec("INSERT INTO sessions VALUES ('sessao-da-origem')");
        $database->exec("INSERT INTO migrations VALUES (1, 'sqlite_schema', 1)");

        return $database;
    }

    private function destination(): PDO
    {
        $database = $this->mysqlDatabase();
        $database->exec('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT, email VARCHAR(191) NOT NULL UNIQUE) ENGINE=InnoDB');
        $database->exec('CREATE TABLE options (id BIGINT PRIMARY KEY, user_id BIGINT UNSIGNED, label TEXT, value BLOB, encrypted TEXT, FOREIGN KEY (user_id) REFERENCES users(id)) ENGINE=InnoDB');
        $database->exec('CREATE TABLE sessions (id VARCHAR(191) PRIMARY KEY) ENGINE=InnoDB');
        $database->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY, migration VARCHAR(191), batch INTEGER) ENGINE=InnoDB');
        $database->exec("INSERT INTO users VALUES (99, 'destino@example.test')");
        $database->exec("INSERT INTO migrations VALUES (1, 'mysql_schema', 1)");

        return $database;
    }
}
