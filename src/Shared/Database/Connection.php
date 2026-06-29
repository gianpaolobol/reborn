<?php

declare(strict_types=1);

namespace Reborn\Shared\Database;

use PDO;

final class Connection
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (($this->config['connection'] ?? 'sqlite') === 'sqlite') {
            $database = $this->config['database'];
            $directory = dirname($database);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $this->pdo = new PDO('sqlite:' . $database);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['maria_host'],
                $this->config['maria_port'],
                $this->config['maria_database']
            );
            $this->pdo = new PDO($dsn, $this->config['maria_user'], $this->config['maria_password']);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this->pdo;
    }
}
