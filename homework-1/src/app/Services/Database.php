<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

class Database
{
    public function __construct(private readonly Medoo $db)
    {
    }

    public static function make(): self
    {
        return container(self::class);
    }

    public function query(): Medoo
    {
        return $this->db;
    }

    public function migrate(string $schemaPath): void
    {
        $this->db->pdo->exec(file_get_contents($schemaPath));
    }
}
