<?php

declare(strict_types=1);

namespace Phibble\Source;

use PDO;
use PDOStatement;
use Phibble\Column\Infer;
use Phibble\DataFrame;
use Phibble\Exception\ColumnNotFound;
use Phibble\Sql\Dialect;
use Phibble\Type;

/**
 * Runs SQL with bindings through PDO and loads the result eagerly.
 *
 * The SQL, bindings and connection are public so that a later lazy SQL
 * table can start from a source and compile methods into the query instead
 * of loading rows.
 */
final readonly class PdoSource implements Source
{
    /**
     * @param array<int|string, mixed> $bindings Positional (list) or named.
     * @param array<string, Type|string> $types
     */
    public function __construct(
        public PDO $pdo,
        public string $sql,
        public array $bindings = [],
        public array $types = [],
    ) {}

    public function read(): DataFrame
    {
        $statement = $this->pdo->prepare($this->sql);
        foreach ($this->bindings as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':'.ltrim($key, ':');
            $statement->bindValue($parameter, self::bindable($value), match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();

        $columns = self::fetchColumns($statement);

        // Columns are typed as the database declared them, so text stays
        // text; only values the driver leaves untyped (SQLite's, or other
        // drivers') are guessed from their text. Explicit types win.
        $declared = Dialect::tryFor($this->pdo)?->resultTypes($statement, $columns) ?? [];
        $positions = [];
        for ($i = 0; $i < count($columns); $i++) {
            $meta = $statement->getColumnMeta($i);
            // A repeated name keeps the last column, as PDO::FETCH_ASSOC does.
            $positions[$meta === false ? "column{$i}" : $meta['name']] = $i;
        }
        foreach (array_keys($this->types) as $name) {
            if (! array_key_exists($name, $positions)) {
                throw ColumnNotFound::named((string) $name, array_map(strval(...), array_keys($positions)));
            }
        }

        // Each raw column is released once it is typed.
        $built = [];
        foreach ($positions as $name => $i) {
            $name = (string) $name;
            $type = $this->types[$name] ?? $declared[$i] ?? null;
            $built[$name] = $columns[$i] === []
                ? Infer::column([], $type, $name)
                : Infer::fromDatabase($columns[$i], $type, $name);
            $columns[$i] = null;
        }

        return DataFrame::fromColumns($built);
    }

    /**
     * A result's values by column position. Repeated strings in a column
     * share one string; a column whose values turn out to be mostly
     * distinct stops pooling, so free text pays nothing.
     *
     * @internal
     *
     * @return list<list<mixed>>
     */
    public static function fetchColumns(PDOStatement $statement): array
    {
        $columns = array_fill(0, $statement->columnCount(), []);
        $pools = array_fill(0, $statement->columnCount(), []);
        $count = 0;
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            foreach ($row as $i => $value) {
                $columns[$i][] = is_string($value) && isset($pools[$i]) ? $pools[$i][$value] ??= $value : $value;
            }
            if (++$count % 4096 === 0) {
                foreach ($pools as $i => $pool) {
                    if (count($pool) > $count / 2) {
                        unset($pools[$i]);
                    }
                }
            }
        }

        return $columns;
    }

    private static function bindable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \Stringable => (string) $value,
            default => $value,
        };
    }
}
