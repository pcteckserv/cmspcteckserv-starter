<?php

namespace App\Services\Deployment;

use Generator;
use PDO;
use RuntimeException;
use Throwable;

/** Formato de cópia autenticado, partilhado pelo compilador e pelo instalador autónomo. */
class DatabaseSnapshot
{
    private const ITERATIONS = 210000;

    public function export(PDO $database, string $path, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $applicationKey): void
    {
        if ($password === '' || $applicationKey === '') {
            throw new RuntimeException('A exportação da base de dados exige uma palavra-passe e uma chave da aplicação válidas.');
        }

        $driver = $this->driver($database);
        $tables = $this->tables($database);
        $salt = random_bytes(32);
        $key = hash_pbkdf2('sha256', $password, $salt, self::ITERATIONS, 32, true);
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível criar a cópia da base de dados.');
        }
        chmod($path, 0600);
        $index = 0;

        try {
            $this->write($stream, json_encode(['format' => 1, 'salt' => base64_encode($salt)], JSON_THROW_ON_ERROR)."\n");
            $this->encrypt($stream, ['type' => 'header', 'driver' => $driver, 'key' => $applicationKey, 'tables' => $tables], $key, $index++);
            if ($driver === 'mysql') {
                $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $database->beginTransaction();
            foreach ($tables as $table) {
                $quoted = $this->quote($table, $driver);
                $sql = $driver === 'mysql'
                    ? $database->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1]
                    : $database->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ".$database->quote($table))->fetchColumn();
                $columns = $driver === 'mysql'
                    ? $database->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC)
                    : $database->query('PRAGMA table_xinfo('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC);
                $insertable = array_column(array_filter($columns, fn (array $column) => $driver === 'mysql'
                    ? ! preg_match('/(?:VIRTUAL|STORED) GENERATED/', $column['Extra'])
                    : ($column['hidden'] ?? 0) === 0), $driver === 'mysql' ? 'Field' : 'name');
                $this->encrypt($stream, ['type' => 'schema', 'table' => $table, 'sql' => $sql], $key, $index++);
                $selection = implode(', ', array_map(fn (string $column) => $this->quote($column, $driver), $insertable));
                $offset = 0;
                do {
                    $rows = $database->query('SELECT '.$selection.' FROM '.$quoted.' LIMIT 500 OFFSET '.$offset)->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $values = array_map(fn ($value) => is_string($value) ? ['bytes' => base64_encode($value)] : $value, $row);
                        $this->encrypt($stream, ['type' => 'row', 'table' => $table, 'values' => $values], $key, $index++);
                    }
                    $offset += count($rows);
                } while (count($rows) === 500);
            }
            if ($driver === 'sqlite') {
                foreach ($database->query("SELECT sql FROM sqlite_master WHERE type IN ('index', 'trigger') AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
                    $this->encrypt($stream, ['type' => 'after', 'sql' => $sql], $key, $index++);
                }
            }
            $this->encrypt($stream, ['type' => 'end', 'records' => $index], $key, $index);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        } finally {
            fclose($stream);
        }
    }

    /** Valida toda a cópia antes de permitir qualquer alteração no destino. */
    public function inspect(string $path, #[\SensitiveParameter] string $password): array
    {
        $header = null;
        $schemas = [];
        $ended = false;
        foreach ($this->records($path, $password) as $index => $record) {
            if ($ended || ($index === 0 && ($record['type'] ?? '') !== 'header')) {
                throw new RuntimeException('A estrutura da cópia da base de dados não é válida.');
            }
            switch ($record['type'] ?? '') {
                case 'header':
                    if ($header !== null || ! in_array($record['driver'] ?? '', ['mysql', 'sqlite'], true)
                        || ! is_string($record['key'] ?? null) || $record['key'] === '' || ! is_array($record['tables'] ?? null)) {
                        throw new RuntimeException('O cabeçalho da cópia não é válido.');
                    }
                    $header = $record;
                    foreach ($header['tables'] as $table) {
                        $this->quote($table, $header['driver']);
                    }
                    break;
                case 'schema':
                    if (! in_array($record['table'] ?? null, $header['tables'], true) || isset($schemas[$record['table']]) || ! is_string($record['sql'] ?? null)) {
                        throw new RuntimeException('A estrutura de uma tabela não é válida.');
                    }
                    $schemas[$record['table']] = true;
                    break;
                case 'row':
                    if (! isset($schemas[$record['table'] ?? '']) || ! is_array($record['values'] ?? null)) {
                        throw new RuntimeException('Os dados de uma tabela não são válidos.');
                    }
                    foreach ($record['values'] as $column => $value) {
                        $this->quote($column, $header['driver']);
                        if (is_array($value) && (! is_string($value['bytes'] ?? null) || base64_decode($value['bytes'], true) === false)) {
                            throw new RuntimeException('Um valor da cópia não é válido.');
                        }
                    }
                    break;
                case 'after':
                    if ($header['driver'] !== 'sqlite' || ! is_string($record['sql'] ?? null)) {
                        throw new RuntimeException('A estrutura adicional não é válida.');
                    }
                    break;
                case 'end':
                    $ended = ($record['records'] ?? -1) === $index && count($schemas) === count($header['tables']);
                    if (! $ended) {
                        throw new RuntimeException('A cópia da base de dados está incompleta.');
                    }
                    break;
                default:
                    throw new RuntimeException('A cópia contém um registo desconhecido.');
            }
        }
        if (! $ended || $header === null) {
            throw new RuntimeException('A cópia da base de dados está incompleta.');
        }

        return $header;
    }

    public function restore(PDO $database, string $path, #[\SensitiveParameter] string $password, bool $replace = false): array
    {
        $header = $this->inspect($path, $password);
        $driver = $this->driver($database);
        if ($header['driver'] === 'sqlite' && $driver === 'mysql') {
            return $this->restoreSqliteToMysql($database, $path, $password, $header, $replace);
        }
        if ($header['driver'] !== $driver) {
            throw new RuntimeException('Esta combinação de motores não é suportada. É possível migrar de SQLite para MySQL ou restaurar para o mesmo motor.');
        }
        $existing = $this->tables($database);
        if ($existing !== [] && ! $replace) {
            throw new RuntimeException('A base de dados de destino não está vazia. Confirme explicitamente a sua substituição.');
        }
        $database->exec($driver === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys=OFF');
        try {
            if ($driver === 'sqlite') {
                $database->beginTransaction();
            }
            foreach ($existing as $table) {
                $database->exec('DROP TABLE '.$this->quote($table, $driver));
            }
            foreach ($this->records($path, $password) as $record) {
                if ($record['type'] === 'schema') {
                    $database->exec($record['sql']);
                }
            }
            if ($driver === 'mysql') {
                $database->beginTransaction();
            }
            $statements = [];
            foreach ($this->records($path, $password) as $record) {
                if ($record['type'] === 'after') {
                    $database->exec($record['sql']);
                } elseif ($record['type'] === 'row') {
                    $this->insertRow($database, $record, $driver, $statements);
                }
            }
            // Sessões e trabalhos pendentes pertencem ao ambiente de origem.
            foreach (array_intersect($header['tables'], ['sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches', 'password_reset_tokens']) as $table) {
                $database->exec('DELETE FROM '.$this->quote($table, $driver));
            }
            if ($database->inTransaction()) {
                $database->commit();
            }
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        } finally {
            $database->exec($driver === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=1' : 'PRAGMA foreign_keys=ON');
        }

        return $header;
    }

    /** Importa apenas dados; a estrutura MySQL é criada pelas migrations da aplicação. */
    private function restoreSqliteToMysql(PDO $database, string $path, #[\SensitiveParameter] string $password, array $header, bool $replace): array
    {
        $tables = $this->tables($database);
        if ($tables !== [] && ! $replace) {
            throw new RuntimeException('A base de dados de destino não está vazia. Confirme explicitamente a sua substituição.');
        }
        if ($database->inTransaction()) {
            throw new RuntimeException('A migração exige uma ligação sem transações em curso.');
        }
        $columns = [];
        foreach ($header['tables'] as $table) {
            if (! in_array($table, $tables, true)) {
                throw new RuntimeException('Faltam tabelas no destino. Execute primeiro as migrations da aplicação e dos plugins em MySQL.');
            }
            $columns[$table] = array_column($database->query('SHOW COLUMNS FROM '.$this->quote($table, 'mysql'))->fetchAll(PDO::FETCH_ASSOC), 'Field');
        }
        $engines = $database->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
        if (array_filter($engines, fn ($engine) => strcasecmp((string) $engine, 'InnoDB') !== 0)) {
            throw new RuntimeException('A migração exige tabelas InnoDB para proteger os dados em caso de falha.');
        }
        foreach ($this->records($path, $password) as $record) {
            if ($record['type'] === 'after' && preg_match('/^\s*CREATE\s+(?:TEMP(?:ORARY)?\s+)?TRIGGER\b/i', $record['sql'])) {
                throw new RuntimeException('A migração de triggers SQLite exige uma adaptação prévia para MySQL.');
            }
            if ($record['type'] === 'row' && array_diff(array_keys($record['values']), $columns[$record['table']]) !== []) {
                throw new RuntimeException('Faltam colunas no destino. Atualize a aplicação e execute as migrations antes de importar.');
            }
        }

        $foreignKeys = (int) $database->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn();
        $sqlMode = (string) $database->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $strictMode = implode(',', array_unique(array_merge(array_filter(explode(',', $sqlMode)), ['STRICT_ALL_TABLES', 'NO_AUTO_VALUE_ON_ZERO'])));
        $database->exec('SET SESSION sql_mode = '.$database->quote($strictMode));
        $database->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $database->beginTransaction();
            foreach ($tables as $table) {
                // O histórico de migrations deve corresponder à estrutura MySQL efetivamente criada.
                if ($table !== 'migrations') {
                    $database->exec('DELETE FROM '.$this->quote($table, 'mysql'));
                }
            }
            $statements = [];
            foreach ($this->records($path, $password) as $record) {
                if ($record['type'] !== 'row' || $record['table'] === 'migrations'
                    || in_array($record['table'], ['sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches', 'password_reset_tokens'], true)) {
                    continue;
                }
                $this->insertRow($database, $record, 'mysql', $statements);
            }
            $this->assertMysqlForeignKeys($database);
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        } finally {
            $database->exec('SET FOREIGN_KEY_CHECKS='.$foreignKeys);
            $database->exec('SET SESSION sql_mode = '.$database->quote($sqlMode));
        }

        return $header;
    }

    private function insertRow(PDO $database, array $record, string $driver, array &$statements): void
    {
        $values = array_map(fn ($value) => is_array($value) ? base64_decode($value['bytes'], true) : $value, $record['values']);
        $columns = implode(', ', array_map(fn (string $name) => $this->quote($name, $driver), array_keys($values)));
        $key = $record['table'].'/'.$columns;
        $statement = $statements[$key] ??= $database->prepare('INSERT INTO '.$this->quote($record['table'], $driver).' ('.$columns.') VALUES ('.implode(', ', array_fill(0, count($values), '?')).')');
        foreach (array_values($values) as $offset => $value) {
            $statement->bindValue($offset + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $statement->execute();
    }

    private function assertMysqlForeignKeys(PDO $database): void
    {
        $keys = $database->query('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
        $groups = [];
        $schema = (string) $database->query('SELECT DATABASE()')->fetchColumn();
        foreach ($keys as $key) {
            if ($key['REFERENCED_TABLE_SCHEMA'] !== $schema) {
                throw new RuntimeException('A migração não suporta relações com outras bases de dados.');
            }
            $groups[$key['TABLE_NAME'].'/'.$key['CONSTRAINT_NAME']][] = $key;
        }
        // MySQL não verifica retroativamente as relações ao reativar FOREIGN_KEY_CHECKS.
        foreach ($groups as $columns) {
            $joins = $notNull = [];
            foreach ($columns as $column) {
                $local = 'child.'.$this->quote($column['COLUMN_NAME'], 'mysql');
                $parent = 'parent.'.$this->quote($column['REFERENCED_COLUMN_NAME'], 'mysql');
                $joins[] = $local.' = '.$parent;
                $notNull[] = $local.' IS NOT NULL';
            }
            $key = $columns[0];
            $query = 'SELECT 1 FROM '.$this->quote($key['TABLE_NAME'], 'mysql').' child WHERE '.implode(' AND ', $notNull)
                .' AND NOT EXISTS (SELECT 1 FROM '.$this->quote($key['REFERENCED_TABLE_NAME'], 'mysql').' parent WHERE '.implode(' AND ', $joins).') LIMIT 1';
            if ($database->query($query)->fetchColumn() !== false) {
                throw new RuntimeException('A cópia contém relações inválidas entre tabelas. Os dados do destino foram preservados.');
            }
        }
    }

    public function tables(PDO $database): array
    {
        if ($this->driver($database) === 'sqlite') {
            return $database->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        }

        $tables = [];
        foreach ($database->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$name, $type]) {
            if ($type !== 'BASE TABLE') {
                throw new RuntimeException('A exportação de bases de dados com vistas exige uma cópia nativa do servidor.');
            }
            $tables[] = $name;
        }
        if ($database->query('SHOW TRIGGERS')->fetch() !== false) {
            throw new RuntimeException('A exportação de bases de dados com triggers exige uma cópia nativa do servidor.');
        }
        sort($tables);

        return $tables;
    }

    private function driver(PDO $database): string
    {
        $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('A cópia da base de dados suporta MySQL, MariaDB e SQLite.');
        }

        return $driver;
    }

    private function quote(string $name, string $driver): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/D', $name)) {
            throw new RuntimeException('O nome de uma tabela ou coluna não é válido.');
        }

        return $driver === 'mysql' ? '`'.$name.'`' : '"'.$name.'"';
    }

    private function encrypt($stream, #[\SensitiveParameter] array $record, #[\SensitiveParameter] string $key, int $index): void
    {
        $nonce = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(json_encode($record, JSON_THROW_ON_ERROR), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'deployment:'.$index);
        if ($encrypted === false) {
            throw new RuntimeException('Não foi possível proteger a cópia da base de dados.');
        }
        $this->write($stream, base64_encode($nonce.$tag.$encrypted)."\n");
    }

    private function records(string $path, #[\SensitiveParameter] string $password): Generator
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('A cópia da base de dados não está disponível.');
        }
        try {
            $format = json_decode(fgets($stream) ?: '', true, flags: JSON_THROW_ON_ERROR);
            $salt = base64_decode($format['salt'] ?? '', true);
            if (($format['format'] ?? null) !== 1 || $salt === false || strlen($salt) !== 32) {
                throw new RuntimeException('O formato da cópia da base de dados não é válido.');
            }
            $key = hash_pbkdf2('sha256', $password, $salt, self::ITERATIONS, 32, true);
            $index = 0;
            while (($line = fgets($stream)) !== false) {
                $bytes = base64_decode(trim($line), true);
                $plain = $bytes === false || strlen($bytes) < 29 ? false : openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'deployment:'.$index);
                if ($plain === false) {
                    throw new RuntimeException('A palavra-passe não é válida ou a cópia da base de dados foi alterada.');
                }
                yield $index++ => json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
            }
        } finally {
            fclose($stream);
        }
    }

    private function write($stream, string $bytes): void
    {
        if (fwrite($stream, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Não foi possível gravar a cópia da base de dados. Verifique o espaço disponível.');
        }
    }
}
