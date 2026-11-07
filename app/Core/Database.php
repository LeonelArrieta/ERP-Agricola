<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $database = (string) $this->config->get('db_name');
        if ($database === '') {
            throw new \RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->get('db_host'),
            $this->config->get('db_port'),
            $database
        );

        $this->connection = new PDO(
            $dsn,
            (string) $this->config->get('db_user'),
            (string) $this->config->get('db_password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $this->connection;
    }
}
