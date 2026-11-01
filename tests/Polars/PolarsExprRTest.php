<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Polars\PolarsTable;

use function Phibble\Expr\{coalesce, col, days, hours, ntile, parse_date_time, seconds};

// The R cases of tests/ExprRTest.php, on PolarsTable.

it('matches dates and datetimes by value in isIn() in Polars', function () {
    $df = DataFrame::fromCsvString("d,t\n2024-01-01,2024-01-01 10:00:00\n2024-02-01,2024-02-01 00:00:00\nNA,NA\n", types: 'DT');
    $date = fn (string $s) => new DateTimeImmutable($s, new DateTimeZone('UTC'));
    $pipeline = fn ($t) => $t->mutate([
        'a' => col('d')->isIn([$date('2024-02-01')]),
        'n' => col('d')->isIn([$date('2024-02-01'), null]),
        'b' => col('t')->isIn([$date('2024-01-01 10:00:00')]),
        'c' => col('t')->isIn([$date('2024-02-01')]),
    ]);

    expect(native(fn () => $pipeline(pt($df)))->collect()->select(['a', 'n', 'b', 'c'])->toRows())
        ->toBe($pipeline($df)->select(['a', 'n', 'b', 'c'])->toRows());
});

it('skips NaN in coalesce() in Polars', function () {
    $df = DataFrame::fromColumns(['x' => [NAN, 1.0, null], 'y' => [5.0, 6.0, 7.0]]);

    expect(native(fn () => pt($df)->mutate(['c' => coalesce(col('x'), col('y'))]))->column('c'))->toBe([5.0, 1.0, 7.0]);
});

it('buckets with ntile(x, n) in Polars', function () {
    expect(native(fn () => pt(DataFrame::fromColumns(['v' => [5, 1, 3, 2]]))->mutate(['b' => ntile(col('v'), 2)]))->column('b'))->toBe([2, 1, 2, 1]);
});

it('parses orders without a year into year 0 in Polars, and rejects a day without a month', function () {
    $df = DataFrame::fromColumns(['s' => ['03-15', '02-29', '13-01', null]]);
    $out = native(fn () => pt($df)->mutate(['o' => parse_date_time(col('s'), 'md')]))->column('o');

    expect(array_map(fn ($v) => $v?->format('Y-m-d H:i:s'), $out))->toBe(['0000-03-15 00:00:00', '0000-02-29 00:00:00', null, null])
        ->and(fn () => pt($df)->mutate(['o' => parse_date_time(col('s'), ['md', 'd'])]))->toThrow(InvalidArgumentException::class, "order 'd' has a day but no month");
});

it('keeps fractional seconds, and gives a datetime for a date plus hours, in Polars', function () {
    $df = DataFrame::fromCsvString("t,d\n2024-01-31 00:00:00,2024-01-31\n", types: 'TD');
    $out = pt($df)->mutate(['s' => col('t')->add(seconds(1.5)), 'h' => col('d')->add(hours(1)), 'dd' => col('d')->add(days(2))]);

    expect($out->column('s')[0]->format('Y-m-d H:i:s.u'))->toBe('2024-01-31 00:00:01.500000')
        ->and($out->column('h')[0]->format('Y-m-d H:i:s'))->toBe('2024-01-31 01:00:00')
        ->and($out->types()['h'])->toBe(Phibble\Type::DateTime)
        ->and($out->column('dd')[0]->format('Y-m-d'))->toBe('2024-02-02');
});
