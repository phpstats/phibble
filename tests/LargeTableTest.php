<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Type;

use function Phibble\Expr\{col, n, str_sub, str_to_upper};
use function Phibble\tibble;

/*
 * Tables larger than the 4096-row blocks in which the CSV reader packs
 * mostly distinct columns and the evaluator stops sharing repeated strings.
 */

function largeCsv(array $header, int $rows, Closure $row): string
{
    $lines = [implode(',', $header)];
    for ($i = 0; $i < $rows; $i++) {
        $lines[] = implode(',', $row($i));
    }

    return implode("\n", $lines)."\n";
}

it('reads distinct columns across many blocks exactly', function () {
    $rows = 10_000;
    $csv = largeCsv(['id', 'x', 's', 'day'], $rows, fn (int $i) => [
        $i,
        $i % 997 === 3 ? 'NA' : sprintf('%.3f', $i / 7),
        "s{$i}",
        date('Y-m-d', 86400 * $i),
    ]);
    $df = DataFrame::fromCsvString($csv);

    $x = [];
    for ($i = 0; $i < $rows; $i++) {
        $x[] = $i % 997 === 3 ? null : (float) sprintf('%.3f', $i / 7);
    }
    expect($df->types())->toBe(['id' => Type::Int, 'x' => Type::Float, 's' => Type::String, 'day' => Type::Date])
        ->and($df->id)->toBe(range(0, $rows - 1))
        ->and($df->x)->toBe($x)
        ->and($df->s)->toBe(array_map(fn (int $i) => "s{$i}", range(0, $rows - 1)))
        ->and($df->day[9_999]->format('Y-m-d'))->toBe(date('Y-m-d', 86400 * 9_999));
});

it('guesses from rows sampled across blocks and reports a failure in a later block', function () {
    // 10,000 rows sample every 10th row and the last, so row 7001 is not seen.
    $csv = largeCsv(['id'], 10_000, fn (int $i) => [$i === 7001 ? 'oops' : $i]);
    $warnings = [];
    set_error_handler(function (int $level, string $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    }, E_USER_WARNING);
    try {
        $df = DataFrame::fromCsvString($csv);
    } finally {
        restore_error_handler();
    }

    expect($df->types())->toBe(['id' => Type::Int])
        ->and($df->id[7001])->toBeNull()
        ->and($df->id[7002])->toBe(7002)
        ->and($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain("column 'id': 1 value could not be parsed as int");
});

it('keeps NUL bytes and empty strings in a distinct column', function () {
    $csv = largeCsv(['s'], 9_000, fn (int $i) => ['"'.match ($i) {
        5000 => "a\0b",
        6000 => '',
        default => "v{$i}",
    }.'"']);
    $df = DataFrame::fromCsvString($csv, na: []);

    expect($df->s[5000])->toBe("a\0b")
        ->and($df->s[6000])->toBe('')
        ->and($df->s[8999])->toBe('v8999')
        ->and($df->nrow())->toBe(9_000);
});

it('groups by several keys with NA, numeric-looking strings, and signed zeros', function () {
    $df = tibble(
        s: ['1', '01', '1', null, '01', null, '1.0'],
        f: [0.0, -0.0, 0.0, 1.5, NAN, 1.5, NAN],
        b: [true, true, false, null, true, null, true],
    );

    $counts = $df->groupBy(['s', 'f', 'b'])->summarize(['n' => n()], groups: 'drop');

    expect(array_map(fn (array $r) => json_encode(array_map(fn ($v) => is_float($v) && is_nan($v) ? 'NaN' : $v, $r)), $counts->toRows()))->toBe([
        '{"s":"01","f":-0,"b":true,"n":1}',
        '{"s":"01","f":"NaN","b":true,"n":1}',
        '{"s":"1","f":0,"b":false,"n":1}',
        '{"s":"1","f":0,"b":true,"n":1}',
        '{"s":"1.0","f":"NaN","b":true,"n":1}',
        '{"s":null,"f":1.5,"b":null,"n":2}',
    ]);
});

it('computes string functions of repeated and distinct values alike', function () {
    $repeated = array_map(fn (int $i) => sprintf('2024-%02d-%02d', $i % 12 + 1, $i % 28 + 1), range(0, 9_999));
    $distinct = array_map(fn (int $i) => "id-{$i}-x", range(0, 9_999));
    $repeated[17] = null;
    $df = tibble(d: $repeated, s: $distinct);

    $out = $df->mutate([
        'month' => str_sub(col('d'), 1, 7),
        'upper' => str_to_upper(col('s')),
        'tail' => str_sub(col('s'), -2),
    ]);

    expect($out->month)->toBe(array_map(fn (?string $d) => $d === null ? null : substr($d, 0, 7), $repeated))
        ->and($out->upper)->toBe(array_map(strtoupper(...), $distinct))
        ->and($out->tail)->toBe(array_map(fn (string $s) => substr($s, -2), $distinct));
});
