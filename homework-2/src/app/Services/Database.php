<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

class Database
{
    public function __construct(private readonly Medoo $medoo) {}

    public function query(): Medoo
    {
        return $this->medoo;
    }

    public function migrate(string $schemaPath): void
    {
        if (!file_exists($schemaPath)) {
            return;
        }

        $sql = trim((string) file_get_contents($schemaPath));
        if ($sql === '') {
            return;
        }

        $this->medoo->pdo->exec($sql);
    }
}