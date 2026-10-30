<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Type;

use function Phibble\tbl;

/*
 * PostgreSQL and MySQL report each result column's declared type, so text
 * stays text and only untyped values are guessed. These run when
 * PHIBBLE_TEST_PGSQL / PHIBBLE_TEST_MYSQL are set (see SqlConnections.php).
 */

function server(string $driver): PDO
{
    $connect = sqlConnections()[$driver] ?? null;
    if ($connect === null) {
        test()->markTestSkipped("Set PHIBBLE_TEST_".strtoupper($driver).' to run this.');
    }

    return $connect();
}

/** Every way of reading a query: eager, lazy, and one column of a lazy table. */
function readings(PDO $pdo, string $sql): array
{
    return [
        'fromPdo' => DataFrame::fromPdo($pdo, $sql),
        'tbl' => tbl($pdo, $sql)->collect(),
    ];
}

it('keeps PostgreSQL text as text and types the rest as declared', function () {
    $pdo = server('pgsql');
    $sql = <<<'SQL'
        SELECT 'TRUE'::text AS flag, '2024-01-02'::varchar AS day_text, '2024-02-30'::text AS bad_day,
               '123'::text AS digits, 'a'::char(2) AS padded, '{"a": 1}'::json AS doc,
               TIME '10:00' AS at, true AS ok, 2::int8 AS n, 1.5::float8 AS x,
               'Infinity'::float8 AS inf, DATE '2024-01-02' AS day,
               TIMESTAMP '2024-01-02 03:04:05' AS stamp
        SQL;

    foreach (readings($pdo, $sql) as $how => $df) {
        expect($df->types())->toBe([
            'flag' => Type::String, 'day_text' => Type::String, 'bad_day' => Type::String,
            'digits' => Type::String, 'padded' => Type::String, 'doc' => Type::String,
            'at' => Type::String, 'ok' => Type::Bool, 'n' => Type::Int, 'x' => Type::Float,
            'inf' => Type::Float, 'day' => Type::Date, 'stamp' => Type::DateTime,
        ], $how)
            ->and($df->row(0)->toArray())->toMatchArray([
                'flag' => 'TRUE', 'day_text' => '2024-01-02', 'bad_day' => '2024-02-30',
                'digits' => '123', 'padded' => 'a ', 'at' => '10:00:00', 'ok' => true, 'n' => 2,
                'x' => 1.5, 'inf' => INF,
            ]);
    }
    expect(tbl($pdo, $sql)->column('flag'))->toBe(['TRUE']);
});

it('reads PostgreSQL timestamptz with an hour-only offset', function () {
    $pdo = server('pgsql');
    $pdo->exec("SET TIME ZONE 'America/New_York'");

    $df = DataFrame::fromPdo($pdo, "SELECT TIMESTAMPTZ '2024-01-02 03:04:05+00' AS at");

    expect($df->type('at'))->toBe(Type::DateTime)
        ->and($df->column('at')[0]->getTimestamp())->toBe((new DateTimeImmutable('2024-01-02 03:04:05Z'))->getTimestamp());
});

it('reads decimals as doubles, whole-number decimals as integers, and keeps them exact on request', function (string $driver) {
    $pdo = server($driver);
    $cast = fn (string $v, string $t) => "CAST({$v} AS {$t})";
    $sql = 'SELECT '.$cast('19.99', 'DECIMAL(10,2)').' AS price, '
        .$cast('12345678901234567.89', 'DECIMAL(30,2)').' AS big, '
        .$cast('6', 'DECIMAL(10,0)').' AS whole';

    foreach (readings($pdo, $sql) as $how => $df) {
        expect($df->types())->toBe(['price' => Type::Float, 'big' => Type::Float, 'whole' => Type::Int], $how)
            ->and($df->column('price'))->toBe([19.99])
            ->and($df->column('big'))->toBe([12345678901234567.89]);
    }

    $exact = DataFrame::fromPdo($pdo, $sql, types: ['big' => 'string']);
    expect($exact->column('big'))->toBe(['12345678901234567.89']);
})->with(['pgsql', 'mysql']);

it('keeps MySQL text as text and types the rest as declared', function () {
    $pdo = server('mysql');
    $sql = "SELECT 'TRUE' AS flag, '2024-02-30' AS bad_day, '123' AS digits, CAST('{}' AS JSON) AS doc, "
        ."CAST(2 AS SIGNED) AS n, CAST(1.5 AS DOUBLE) AS x, DATE '2024-01-02' AS day, "
        ."TIMESTAMP '2024-01-02 03:04:05' AS stamp";

    foreach (readings($pdo, $sql) as $how => $df) {
        expect($df->types())->toBe([
            'flag' => Type::String, 'bad_day' => Type::String, 'digits' => Type::String, 'doc' => Type::String,
            'n' => Type::Int, 'x' => Type::Float, 'day' => Type::Date, 'stamp' => Type::DateTime,
        ], $how)
            ->and($df->row(0)->toArray())->toMatchArray(['flag' => 'TRUE', 'digits' => '123', 'n' => 2, 'x' => 1.5]);
    }
});

it('types empty results as declared', function (string $driver) {
    $pdo = server($driver);

    $df = DataFrame::fromPdo($pdo, "SELECT 'a' AS name, CAST(1 AS ".($driver === 'mysql' ? 'SIGNED' : 'INTEGER').') AS n WHERE 1 = 0');

    expect($df->nrow())->toBe(0)
        ->and($df->types())->toBe(['name' => Type::String, 'n' => Type::Int]);
})->with(['pgsql', 'mysql']);

it('still guesses SQLite values, which carry no declared type', function () {
    $df = DataFrame::fromPdo(new PDO('sqlite::memory:'), "SELECT 'TRUE' AS flag, '2024-01-02' AS day");

    expect($df->types())->toBe(['flag' => Type::Bool, 'day' => Type::Date]);
});
