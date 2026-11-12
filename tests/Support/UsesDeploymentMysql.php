<?php

namespace Tests\Support;

use PDO;

trait UsesDeploymentMysql
{
    private ?PDO $mysql = null;

    private ?string $mysqlSchema = null;

    protected function mysqlDatabase(): PDO
    {
        if ($this->mysql !== null) {
            return $this->mysql;
        }
        $dsn = getenv('DEPLOY_TEST_MYSQL_DSN');
        if (! $dsn) {
            $this->markTestSkipped('Defina DEPLOY_TEST_MYSQL_DSN, DEPLOY_TEST_MYSQL_USER e DEPLOY_TEST_MYSQL_PASSWORD para testar num servidor MySQL isolado.');
        }
        $this->mysql = new PDO(rtrim($dsn, ';').';charset=utf8mb4', getenv('DEPLOY_TEST_MYSQL_USER') ?: '', getenv('DEPLOY_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->mysqlSchema = 'deployment_test_'.bin2hex(random_bytes(8));
        $this->mysql->exec('CREATE DATABASE `'.$this->mysqlSchema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->mysql->exec('USE `'.$this->mysqlSchema.'`');

        return $this->mysql;
    }

    protected function dropMysqlDatabase(): void
    {
        if ($this->mysql !== null && preg_match('/^deployment_test_[a-f0-9]{16}$/D', $this->mysqlSchema ?? '')) {
            if ($this->mysql->inTransaction()) {
                $this->mysql->rollBack();
            }
            $this->mysql->exec('DROP DATABASE `'.$this->mysqlSchema.'`');
        }
        $this->mysql = null;
        $this->mysqlSchema = null;
    }
}
