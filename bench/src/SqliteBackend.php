<?php

declare(strict_types=1);

namespace Phibble\Bench;

use PDO;
use Phibble\Contracts\Table;
use Phibble\DataFrame;
use Phibble\Sql\SqlTable;
use Phibble\Type;

/**
 * Tables in a SQLite file, bench/data/bench.sqlite, built from the CSV files
 * the first time and whenever a CSV file is newer. load() opens a SqlTable on
 * the stored table, as an application would.
 */
final class SqliteBackend implements Backend
{
    private ?PDO $pdo = null;

    public function name(): string
    {
        return 'sqlite';
    }

    public function load(string $path): Table
    {
        $database = dirname($path).'/bench.sqlite';
        if (! is_file($database) || filemtime($database) < filemtime($path)) {
            $this->pdo = null;
            @unlink($database);
            $this->build($database, dirname($path));
        }
        $this->pdo ??= new PDO("sqlite:{$database}");

        return SqlTable::table($this->pdo, basename($path, '.csv'));
    }

    private function build(string $database, string $dir): void
    {
        $pdo = new PDO("sqlite:{$database}");
        foreach (['events', 'dims'] as $table) {
            $df = DataFrame::fromCsv("{$dir}/{$table}.csv");
            $columns = $df->columns();
            // Typed columns: untyped ones store bound values as TEXT, and SQLite
            // orders all TEXT above all numbers.
            $sqlType = fn (string $c) => match ($df->type($c)) {
                Type::Int, Type::Bool => 'INTEGER',
                Type::Float => 'REAL',
                default => 'TEXT',
            };
            $pdo->exec("CREATE TABLE \"{$table}\" (".implode(', ', array_map(fn ($c) => "\"{$c}\" {$sqlType($c)}", $columns)).')');
            $insert = $pdo->prepare("INSERT INTO \"{$table}\" VALUES (".implode(', ', array_fill(0, count($columns), '?')).')');
            $pdo->beginTransaction();
            foreach ($df->toRows() as $row) {
                $insert->execute(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, array_values($row)));
            }
            $pdo->commit();
            $pdo->exec("CREATE INDEX \"{$table}_key\" ON \"{$table}\" (\"key\")");
        }
    }
}
