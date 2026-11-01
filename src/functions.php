<?php

declare(strict_types=1);

/*
 * tibble-style constructors. Each is a thin wrapper over a DataFrame method:
 *
 *     $df = tibble(x: [1, 2, 3], y: fn ($t) => array_map(fn ($x) => $x * 2, $t['x']));
 */

namespace Phibble;

/** Build a DataFrame from named arguments, one per column, as tibble's tibble(). Same rules as DataFrame::tibble(). */
function tibble(mixed ...$columns): DataFrame
{
    return DataFrame::tibble($columns);
}

/** Build a DataFrame from column names, then rows, as tibble's tribble(). Same rules as DataFrame::tribble(). */
function tribble(array $names, mixed ...$cells): DataFrame
{
    return DataFrame::tribble($names, ...$cells);
}

/** Convert tables, rows, columns, or collections into a DataFrame, as tibble's as_tibble(). Same rules as DataFrame::asTibble(). */
function as_tibble(mixed $data, string $nameRepair = 'check_unique'): DataFrame
{
    return DataFrame::asTibble($data, $nameRepair);
}

/** An array as a two-column DataFrame of names and values, as tibble's enframe(). Same rules as DataFrame::enframe(). */
function enframe(array $values, ?string $name = 'name', string $value = 'value'): DataFrame
{
    return DataFrame::enframe($values, $name, $value);
}

/** A two-column DataFrame as a name => value array, or a one-column DataFrame as a list, as tibble's deframe(). */
function deframe(DataFrame $dataFrame): array
{
    return $dataFrame->deframe();
}

/**
 * A lazy SqlTable, as dbplyr's tbl(). Pass a table name, or a SELECT or
 * WITH query with bindings.
 *
 *     tbl($pdo, 'visits');
 *     tbl($pdo, 'SELECT * FROM visits WHERE year = ?', [2024]);
 *
 * @param string $from A table or view name, or a SELECT or WITH query.
 * @param array<int|string, mixed> $bindings Positional (?) or named (:name) bindings, for a query only.
 * @throws \InvalidArgumentException When bindings come with a table name.
 */
function tbl(\PDO $pdo, string $from, array $bindings = []): Sql\SqlTable
{
    if (preg_match('/^\s*(SELECT|WITH)\b/i', $from)) {
        return Sql\SqlTable::query($pdo, $from, $bindings);
    }
    if ($bindings !== []) {
        throw new \InvalidArgumentException(
            "tbl() takes bindings only with a SELECT or WITH query; '{$from}' is read as a table name."
        );
    }

    return Sql\SqlTable::table($pdo, $from);
}

/**
 * A PolarsTable from any Table, or from anything as_tibble() accepts. Requires
 * the php_polars extension.
 *
 *     polars(DataFrame::fromCsv('sales.csv'))->filter(col('units')->gt(0));
 *
 * @throws \RuntimeException When the php_polars extension is not loaded.
 */
function polars(mixed $data): Polars\PolarsTable
{
    if (! extension_loaded('php_polars')) {
        throw new \RuntimeException('polars() needs the php_polars extension (phpstats/phpolars).');
    }

    return Polars\PolarsTable::fromTable($data instanceof Contracts\Table ? $data : DataFrame::asTibble($data));
}
