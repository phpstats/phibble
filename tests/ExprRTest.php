<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Sql\SqlTable;
use Phibble\Time;

use function Phibble\Expr\{coalesce, col, days, hours, minutes, months, ntile, parse_date_time, row_number, seconds, str_pad, weeks, years};

/*
 * Argument rules and edge cases checked against R 4.6.1 with dplyr 1.2.1,
 * stringr 1.6.0, and lubridate 1.9.5. Each expected value is R's.
 */

function utcDate(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date, new DateTimeZone('UTC'));
}

function formatted(array $values, string $format = 'Y-m-d H:i:s.u'): array
{
    return array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format($format) : $v, $values);
}

it('takes at most one argument in row_number(), as dplyr does', function () {
    expect(row_number()->toArray()['args'])->toBe([])
        ->and(exprFrame()->mutate(['r' => row_number(col('i'))])->column('r'))->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and(fn () => row_number(col('x'), col('i')))->toThrow(ArgumentCountError::class, 'row_number() takes at most 1 argument, 2 given.');
});

it('takes x and a required n in ntile(), as dplyr does', function () {
    $df = DataFrame::fromColumns(['v' => [5, 1, 3, 2]]);

    expect($df->mutate(['b' => ntile(col('v'), 2)])->column('b'))->toBe([2, 1, 2, 1])
        ->and($df->mutate(['b' => ntile(col('v'), n: 2)])->column('b'))->toBe([2, 1, 2, 1])
        ->and($df->mutate(['b' => ntile(n: 2)])->column('b'))->toBe([1, 1, 2, 2])
        ->and(fn () => ntile(col('v')))->toThrow(InvalidArgumentException::class, 'ntile() needs n')
        ->and(fn () => ntile(col('v'), 2, 3))->toThrow(ArgumentCountError::class);
});

it('matches dates, datetimes, and times by value in isIn()', function () {
    $df = DataFrame::fromCsvString("d,t,tm\n2024-01-01,2024-01-01 10:00:00,10:30:00\n2024-02-01,2024-02-01 00:00:00,11:00:00\nNA,NA,NA\n", types: 'DTt');
    $out = $df->mutate([
        'in_d' => col('d')->isIn([utcDate('2024-02-01')]),
        'in_dn' => col('d')->isIn([utcDate('2024-02-01'), null]),
        'in_t' => col('t')->isIn([utcDate('2024-01-01 10:00:00')]),
        'in_midnight' => col('t')->isIn([utcDate('2024-02-01')]),
        'in_tm' => col('tm')->isIn([Time::parse('10:30:00')]),
    ]);

    expect($out->column('in_d'))->toBe([false, true, false])
        ->and($out->column('in_dn'))->toBe([false, true, true])
        ->and($out->column('in_t'))->toBe([true, false, false])
        ->and($out->column('in_midnight'))->toBe([false, true, false])
        ->and($out->column('in_tm'))->toBe([true, false, false])
        ->and(fn () => col('d')->isIn([utcDate('2024-01-01'), 'x']))->toThrow(InvalidArgumentException::class, 'may not mix')
        ->and(fn () => col('d')->isIn([new stdClass()]))->toThrow(InvalidArgumentException::class);
});

it('serializes temporal isIn() lists', function () {
    $expr = col('d')->isIn([utcDate('2024-02-01'), null]);
    $array = json_decode(json_encode($expr->toArray()), true);

    expect($array['args'][1])->toBe(['type' => 'lit', 'value' => ['2024-02-01', null], 'dtype' => 'date'])
        ->and(Phibble\Expr\Expr::fromArray($array)->toArray())->toBe($expr->toArray());
});

it('matches dates and datetimes by value in isIn() in SQL', function (Closure $connect) {
    $pdo = $connect();
    $df = DataFrame::fromCsvString("i,d,t\n1,2024-01-01,2024-01-01 10:00:00\n2,2024-02-01,2024-02-01 00:00:00\n3,NA,NA\n", types: 'iDT');
    $pipeline = fn ($t) => $t->arrange(['i'])->mutate([
        'a' => col('d')->isIn([utcDate('2024-02-01')]),
        'n' => col('d')->isIn([utcDate('2024-02-01'), null]),
        'b' => col('t')->isIn([utcDate('2024-01-01 10:00:00')]),
        'c' => col('t')->isIn([utcDate('2024-02-01')]),
    ]);
    $sql = $pipeline(sqlFrom($pdo, $df));

    expect($sql->collect()->select(['a', 'n', 'b', 'c'])->toRows())->toBe($pipeline($df)->select(['a', 'n', 'b', 'c'])->toRows())
        ->and($sql instanceof SqlTable)->toBe($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite');
})->with('connections');

it('skips NaN in coalesce(), as is_na() does', function () {
    $df = DataFrame::fromColumns(['x' => [NAN, 1.0, null], 'y' => [5.0, 6.0, 7.0]]);

    expect($df->mutate(['c' => coalesce(col('x'), col('y'))])->column('c'))->toBe([5.0, 1.0, 7.0]);
});

it('skips NaN in coalesce() in SQL', function (Closure $connect) {
    $pdo = $connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    // x / x is NaN for x = 0. Only PostgreSQL stores NaN; the others give NULL.
    $sql = sqlFrom($pdo, DataFrame::fromColumns(['i' => [1, 2, 3], 'x' => [0.0, 1.0, null], 'y' => [5.0, 6.0, 7.0], 's' => [null, 'a', null], 'u' => ['z', 'b', null]]))
        ->arrange(['i'])
        ->mutate(['q' => col('x')->div(col('x'))]);
    $numbers = $sql->mutate(['c' => coalesce(col('q'), col('y'))]);
    $text = $sql->mutate(['c' => coalesce(col('s'), col('u'))]);

    expect($numbers->collect()->column('c'))->toBe([5.0, 1.0, 7.0])
        ->and($numbers instanceof SqlTable)->toBe($driver !== 'pgsql')
        ->and($text->collect()->column('c'))->toBe(['z', 'a', null])
        ->and($text)->toBeInstanceOf(SqlTable::class);
})->with('connections');

it('requires a one-character pad in str_pad(), as stringr does', function () {
    $df = DataFrame::fromColumns(['s' => ['a'], 'p' => ['xy']]);

    expect($df->mutate(['o' => str_pad(col('s'), 4, pad: 'é')])->column('o'))->toBe(['éééa'])
        ->and(fn () => str_pad(col('s'), 4, pad: 'ab'))->toThrow(InvalidArgumentException::class, "str_pad() pad must be one character of width 1, got 'ab'.")
        ->and(fn () => str_pad(col('s'), 4, pad: ''))->toThrow(InvalidArgumentException::class, 'one character')
        ->and(fn () => str_pad(col('s'), 4, pad: '漢'))->toThrow(InvalidArgumentException::class, 'width 1')
        ->and(fn () => $df->mutate(['o' => str_pad(col('s'), 4, pad: col('p'))]))->toThrow(InvalidArgumentException::class, "got 'xy'");
});

it('parses orders without a year into year 0, as lubridate does', function () {
    $df = DataFrame::fromColumns(['s' => ['03-15', '02-29', '10:30', '13-01', null]]);

    expect(formatted($df->mutate(['o' => parse_date_time(col('s'), 'md')])->column('o'), 'Y-m-d H:i:s'))
        ->toBe(['0000-03-15 00:00:00', '0000-02-29 00:00:00', '0000-10-30 00:00:00', null, null])
        ->and(formatted($df->mutate(['o' => parse_date_time(col('s'), 'HM')])->column('o'), 'Y-m-d H:i:s'))
        ->toBe(['0000-01-01 03:15:00', '0000-01-01 02:29:00', '0000-01-01 10:30:00', '0000-01-01 13:01:00', null]);
});

it('rejects a parse_date_time() order with a day but no month', function () {
    $df = DataFrame::fromColumns(['s' => ['15'], 'o' => ['d']]);

    expect(fn () => parse_date_time(col('s'), 'd'))->toThrow(InvalidArgumentException::class, "order 'd' has a day but no month")
        ->and(fn () => parse_date_time(col('s'), ['md', 'dHM']))->toThrow(InvalidArgumentException::class, "order 'dHM'")
        ->and(fn () => $df->mutate(['x' => parse_date_time(col('s'), col('o'))]))->toThrow(InvalidArgumentException::class, 'has a day but no month');
});

it('keeps fractional seconds and rejects other fractional periods, as lubridate does', function () {
    $df = DataFrame::fromCsvString("t,d\n2024-01-31 00:00:00,2024-01-31\n", types: 'TD');

    expect(formatted($df->mutate(['o' => col('t')->add(seconds(1.5))])->column('o')))->toBe(['2024-01-31 00:00:01.500000'])
        ->and(formatted($df->mutate(['o' => col('t')->add(days(1)->mul(2))])->column('o')))->toBe(['2024-02-02 00:00:00.000000'])
        ->and(formatted($df->mutate(['o' => col('t')->add(days(2.0))])->column('o')))->toBe(['2024-02-02 00:00:00.000000']);
    foreach ([minutes(...), hours(...), days(...), weeks(...), months(...), years(...)] as $period) {
        expect(fn () => $period(1.5))->toThrow(InvalidArgumentException::class, 'needs a whole number, got 1.5');
    }
    expect(fn () => $df->mutate(['o' => col('t')->add(days(1)->mul(2.5))]))->toThrow(InvalidArgumentException::class, 'days() needs a whole number, got 2.5')
        ->and(fn () => DataFrame::fromColumns(['n' => [1.5]])->mutate(['o' => days(col('n'))]))->toThrow(InvalidArgumentException::class, 'got 1.5');
});

it('gives a datetime for a date plus hours, minutes, or seconds, as lubridate does', function () {
    $df = DataFrame::fromCsvString("d\n2024-01-31\nNA\n", types: 'D');
    $out = $df->mutate(['h' => col('d')->add(hours(1)), 's' => col('d')->add(seconds(1.5)), 'm' => col('d')->sub(minutes(30)), 'dd' => col('d')->add(days(1))]);

    expect($out->type('h'))->toBe(Phibble\Type::DateTime)
        ->and(formatted($out->column('h')))->toBe(['2024-01-31 01:00:00.000000', null])
        ->and(formatted($out->column('s')))->toBe(['2024-01-31 00:00:01.500000', null])
        ->and(formatted($out->column('m')))->toBe(['2024-01-30 23:30:00.000000', null])
        ->and($out->type('dd'))->toBe(Phibble\Type::Date);
});
