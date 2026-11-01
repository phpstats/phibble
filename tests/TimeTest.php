<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Polars\PolarsTable;
use Phibble\Sql\SqlTable;
use Phibble\Time;
use Phibble\Type;

use function Phibble\Expr\{col, hour, max, min, minute, second};
use function Phibble\tbl;

/*
 * The time column type, as R's hms. Expected values come from readr 2.2.0,
 * hms 1.1.4, pillar 1.11.1, and lubridate.
 */

function times(array $seconds): array
{
    return array_map(fn ($s) => $s === null ? null : Time::fromSeconds($s), $seconds);
}

it('holds microseconds, which may be negative or past a day', function () {
    expect(Time::of(7, 30)->microseconds)->toBe(27_000_000_000)
        ->and(Time::fromSeconds(90061.25)->seconds())->toBe(90061.25)
        ->and((string) Time::fromSeconds(90061.25))->toBe('25:01:01.25')
        ->and((string) Time::fromSeconds(-3600))->toBe('-01:00:00')
        ->and((string) Time::of(100))->toBe('100:00:00')
        ->and(json_encode(Time::of(1, 0, 1.5)))->toBe('"01:00:01.5"');
});

it('gives the clock time as lubridate does', function () {
    expect([Time::fromSeconds(3725.5)->hour(), Time::fromSeconds(3725.5)->minute(), Time::fromSeconds(3725.5)->second()])->toBe([1, 2, 5.5])
        ->and(Time::fromSeconds(90061)->hour())->toBe(1)
        ->and([Time::fromSeconds(-5)->hour(), Time::fromSeconds(-5)->minute(), Time::fromSeconds(-5)->second()])->toBe([23, 59, 55.0]);
});

it('parses text, keeping fractions, signs, and long hours', function () {
    expect(Time::parse('7:30')?->microseconds)->toBe(27_000_000_000)
        ->and(Time::parse('-01:00:00.5')?->seconds())->toBe(-3600.5)
        ->and(Time::parse('838:59:59')?->seconds())->toBe(3020399.0)
        ->and(Time::parse('1:02 PM')?->seconds())->toBe(46920.0)
        ->and(Time::parse('7:60'))->toBeNull()
        ->and(Time::parse('noon'))->toBeNull();
});

it('guesses and parses times in CSV as readr does', function () {
    // Each value read alone: readr's guess, and parse_time() in seconds.
    $cases = [
        '7:30' => ['time', 27000], '00:07:15' => ['time', 435], '12:00:01.5' => ['time', 43201],
        '1:02 PM' => ['time', 46920], '1:02:03 am' => ['time', 3723], '25:00' => ['time', 90000],
        '24:00:00' => ['time', 86400], '99:59:59' => ['time', 359999], '100:00:00' => ['string', null],
        '-1:00' => ['string', null], '7:60' => ['time', null], '7:5' => ['string', null],
        '12:00 AM' => ['time', 0], '12:00 PM' => ['time', 43200], '13:00 PM' => ['time', 90000],
        '13:00 AM' => ['time', 46800], '00:30 PM' => ['time', 45000], '7:30:60' => ['time', null],
        '7.30' => ['float', null], '1:2:3' => ['string', null], '1:02:3' => ['time', 3723],
        '7:30.5' => ['time', 27000], '7:30:00PM' => ['time', 70200], '07:30:00.' => ['time', 27000],
        '1:02 P.M.' => ['string', null], '1:02 p' => ['string', null], '7:30Z' => ['string', null],
    ];
    $got = [];
    foreach ($cases as $text => $_) {
        set_error_handler(fn () => true, E_USER_WARNING);
        try {
            $guessed = DataFrame::fromCsvString("x\n\"{$text}\"\n")->type('x')->value;
            $parsed = DataFrame::fromCsvString("x\n\"{$text}\"\n", types: 't')->x[0];
        } finally {
            restore_error_handler();
        }
        $got[$text] = [$guessed, $parsed?->seconds() === null ? null : (int) $parsed->seconds()];
    }

    expect($got)->toBe($cases);
});

it('reads a time column the same way with either reader, in chunks too', function () {
    $path = tempnam(sys_get_temp_dir(), 'phibble-time').'.csv';
    file_put_contents($path, "pace,t\n7:30,1:02 PM\n00:07:15,NA\n12:00:01.5,23:59:59\n");
    try {
        $df = DataFrame::fromCsv($path);
        $chunks = iterator_to_array(DataFrame::csvChunks($path, chunkSize: 2), false);
    } finally {
        unlink($path);
    }

    expect($df->types())->toBe(['pace' => Type::Time, 't' => Type::Time])
        ->and(array_map(strval(...), $df->pace))->toBe(['07:30:00', '00:07:15', '12:00:01'])
        ->and($df->t[1])->toBeNull()
        ->and(array_map(fn (DataFrame $c) => $c->types(), $chunks))->toBe(array_fill(0, 2, ['pace' => Type::Time, 't' => Type::Time]))
        ->and($chunks[1]->t[0]?->seconds())->toBe(86399.0);
});

it('prints a time column as tibble does', function () {
    $print = fn (array $seconds) => implode("\n", array_slice(explode("\n", (string) DataFrame::fromColumns(['t' => times($seconds)], ['t' => Type::Time])), 2));

    expect($print([3600, 1.5]))->toBe("  <time>    \n1 01:00:00.0\n2 00:00:01.5")
        ->and($print([3600, 7200]))->toBe("  <time>\n1 01:00 \n2 02:00 ")
        ->and($print([360000, 5]))->toBe("  <time>   \n1 100:00:00\n2  00:00:05")
        ->and($print([-5, 3600]))->toBe("  <time>   \n1 -00:00:05\n2  01:00:00")
        ->and($print([0.25, null]))->toBe("  <time>   \n1 00'00.25\"\n2    NA    ")
        ->and($print([null, 3600, 5]))->toBe("  <time>  \n1       NA\n2 01:00:00\n3 00:00:05")
        ->and($print([null, 5, 75]))->toBe("  <time>\n1    NA \n2 00'05\"\n3 01'15\"")
        ->and($print([-75, null, 0.5]))->toBe("  <time>   \n1 -01'15.0\"\n2     NA   \n3  00'00.5\"")
        ->and($print([3600, 36000, 360000, null]))->toBe("  <time>\n1  01:00\n2  10:00\n3 100:00\n4     NA")
        ->and($print([null, null]))->toBe("  <time>\n1    NA \n2    NA ");
});

it('glimpses a time column as tibble does', function () {
    $df = DataFrame::fromColumns(['t' => times([75, null, 0.5])]);

    expect($df->glimpse())->toBe("Rows: 3\nColumns: 1\n\$ t <time> 00:01:15.0,         NA, 00:00:00.5");
});

it('writes times as readr does, formatted as a column', function () {
    $df = DataFrame::fromColumns(['t' => times([5, 360000, null]), 'u' => times([-3600, 90061.25, 0.5])]);

    expect($df->toCsv())->toBe("t,u\n 00:00:05,-01:00:00.00\n100:00:00,25:01:01.25\nNA,00:00:00.50\n")
        ->and($df->toCsv(quote: 'all', na: '-'))->toBe("\"t\",\"u\"\n\" 00:00:05\",\"-01:00:00.00\"\n\"100:00:00\",\"25:01:01.25\"\n-,\"00:00:00.50\"\n");
});

it('converts times as R does', function () {
    $df = DataFrame::fromColumns(['t' => times([3600, 1.5, null])]);

    expect($df->cast(['t' => 'float'])->t)->toBe([3600.0, 1.5, null])
        ->and($df->cast(['t' => 'int'])->t)->toBe([3600, 1, null])
        ->and($df->cast(['t' => 'string'])->t)->toBe(['01:00:00.0', '00:00:01.5', null])
        ->and(DataFrame::fromColumns(['s' => [5400, 0.5]])->cast(['s' => 'time'])->s)->toEqual(times([5400, 0.5]))
        ->and(DataFrame::fromColumns(['s' => ['7:30', '-00:00:01.25']])->cast(['s' => 'time'])->s)->toEqual(times([27000, -1.25]))
        ->and(DataFrame::fromColumns(['d' => [new DateTimeImmutable('2024-01-02 03:04:05.5', new DateTimeZone('UTC'))]])->cast(['d' => 'time'])->d)->toEqual(times([11045.5]))
        ->and(DataFrame::fromColumns(['t' => times([3600])])->cast(['t' => 'datetime'])->t[0]->format('Y-m-d H:i:s'))->toBe('1970-01-01 01:00:00');
});

it('infers a time column from Time values and keeps it through the verbs', function () {
    $df = DataFrame::fromColumns(['t' => times([3600, 5, 3600, null]), 'n' => [1, 2, 3, 4]]);

    expect($df->type('t'))->toBe(Type::Time)
        ->and($df->arrange(['t'])->n)->toBe([2, 1, 3, 4])
        ->and($df->groupBy(['t'])->summarize(['n' => \Phibble\Expr\n()])->toRows())->toEqual([
            ['t' => Time::fromSeconds(5), 'n' => 1],
            ['t' => Time::fromSeconds(3600), 'n' => 2],
            ['t' => null, 'n' => 1],
        ])
        ->and($df->distinct(['t'])->nrow())->toBe(3)
        ->and($df->summarize(['lo' => min(col('t'), na_rm: true), 'hi' => max(col('t'), na_rm: true)])->toRows())
            ->toEqual([['lo' => Time::fromSeconds(5), 'hi' => Time::fromSeconds(3600)]])
        ->and($df->filter(col('t')->gt('00:30'))->n)->toBe([1, 3])
        ->and($df->filter(col('t')->lt(Time::of(0, 1)))->n)->toBe([2])
        ->and($df->mutate(['later' => col('t')->add(60)])->type('later'))->toBe(Type::Time)
        ->and($df->mutate(['later' => col('t')->add(60)])->later[1])->toEqual(Time::fromSeconds(65))
        ->and($df->mutate(['h' => hour(col('t')), 'm' => minute(col('t')), 's' => second(col('t'))])->select(['h', 'm', 's'])->row(0)->toArray())
            ->toBe(['h' => 1, 'm' => 0, 's' => 0.0])
        ->and($df->toModelData(['t'])['t'])->toBe([3600.0, 5.0, 3600.0, null])
        ->and($df->join(DataFrame::fromColumns(['t' => times([3600]), 'label' => ['hour']]), ['t'])->n)->toBe([1, 3]);
});

it('writes and reads times in JSON as text', function () {
    $df = DataFrame::fromColumns(['t' => times([3600, 1.5, null])]);

    expect($df->toJson())->toBe('[{"t":"01:00:00"},{"t":"00:00:01.5"},{"t":null}]');
});

it('guesses times in SQLite text and keeps SQL time columns as times', function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec("CREATE TABLE runs (pace TEXT, n INTEGER)");
    $pdo->exec("INSERT INTO runs VALUES ('07:30:00', 1), ('06:55:00', 2), (NULL, 3)");

    $runs = tbl($pdo, 'runs');
    $fast = $runs->filter(col('pace')->lt('07:00:00'));

    expect($runs->collect()->type('pace'))->toBe(Type::Time)
        ->and($fast)->toBeInstanceOf(SqlTable::class)
        ->and($fast->collect()->n)->toBe([2]);
});

it('compiles comparisons of time columns and evaluates their functions in memory', function (string $driver) {
    $pdo = server($driver);
    $sql = $driver === 'pgsql'
        ? "SELECT * FROM (VALUES (TIME '07:30:00', 1), (TIME '06:55:00.5', 2), (NULL::time, 3)) AS v(pace, n)"
        : "SELECT CAST('07:30:00' AS TIME) AS pace, 1 AS n UNION ALL SELECT CAST('06:55:00.5' AS TIME(1)), 2 UNION ALL SELECT NULL, 3";
    $runs = tbl($pdo, $sql);
    $fast = $runs->filter(col('pace')->lt(Time::of(7)));
    $hours = $runs->mutate(['h' => hour(col('pace'))]);

    expect($runs->collect()->type('pace'))->toBe(Type::Time)
        ->and($runs->collect()->pace)->toEqual(times([27000, 24900.5, null]))
        ->and($fast)->toBeInstanceOf(SqlTable::class)
        ->and($fast->collect()->n)->toBe([2])
        ->and($hours)->toBeInstanceOf(DataFrame::class)
        ->and($hours->h)->toBe([7, 6, null]);
})->with(['pgsql', 'mysql']);

it('reads MySQL TIME values past a day and below zero', function () {
    $pdo = server('mysql');
    $df = DataFrame::fromPdo($pdo, "SELECT CAST('-838:59:59' AS TIME) AS lo, CAST('100:00:00' AS TIME) AS hi");

    expect($df->types())->toBe(['lo' => Type::Time, 'hi' => Type::Time])
        ->and($df->row(0)->toArray())->toEqual(['lo' => Time::fromSeconds(-3020399), 'hi' => Time::of(100)]);
});

it('moves time columns through Polars as durations', function () {
    $df = DataFrame::fromColumns(['t' => times([3600, -1.5, null, 90061.25]), 'n' => [1, 2, 3, 4]]);
    $polars = PolarsTable::fromTable($df);
    $fast = $polars->filter(col('t')->lt(Time::of(1)));

    expect($polars->types())->toBe(['t' => Type::Time, 'n' => Type::Int])
        ->and($polars->collect()->toRows())->toEqual($df->toRows())
        ->and($fast)->toBeInstanceOf(PolarsTable::class)
        ->and($fast->collect()->n)->toBe([2])
        ->and($polars->mutate(['h' => hour(col('t'))])->collect()->h)->toBe([1, 23, null, 1]);
})->skip(! extension_loaded('php_polars'), 'php_polars extension not loaded');
