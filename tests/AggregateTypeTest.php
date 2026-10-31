<?php

declare(strict_types=1);

use Phibble\DataFrame;
use Phibble\Expr\Expr;

use function Phibble\Expr\{all, any, col, first, iqr, last, max, mean, median, min, nth, quantile, sd, sum, var_};

/** @return array<string, Expr> */
function naAggregates(): array
{
    return [
        'sum_dbl' => sum(col('x')),
        'sum_int' => sum(col('i')),
        'sum_lgl' => sum(col('b')),
        'mean_dbl' => mean(col('x')),
        'mean_int' => mean(col('i')),
        'median_int' => median(col('i')),
        'sd_int' => sd(col('i')),
        'var_dbl' => var_(col('x')),
        'quantile_int' => quantile(col('i'), 0.5, na_rm: true),
        'iqr_int' => iqr(col('i'), na_rm: true),
        'min_int' => min(col('i')),
        'max_dbl' => max(col('x')),
        'min_lgl' => min(col('b')),
        'median_lgl' => median(col('b')),
        'first_lgl' => first(col('b')),
        'min_chr' => min(col('s')),
        'max_date' => max(col('d')),
        'first_int' => first(col('i')),
        'last_chr' => last(col('s')),
        'nth_dbl' => nth(col('x'), 1),
        'any_lgl' => any(col('b')),
        'all_lgl' => all(col('b')),
    ];
}

it('types an aggregate that is NA in every group as dplyr does', function () {
    $expected = json_decode((string) file_get_contents(__DIR__.'/fixtures/expr/types.json'), true);
    $df = DataFrame::fromColumns(
        ['g' => ['a', 'b', 'a', 'b'], 'x' => [null, null, null, null], 'i' => [null, null, null, null],
            'b' => [null, null, null, null], 's' => [null, null, null, null], 'd' => [null, null, null, null]],
        ['x' => 'float', 'i' => 'int', 'b' => 'bool', 's' => 'string', 'd' => 'date'],
    );

    $out = $df->groupBy(['g'])->summarize(naAggregates());
    $types = array_map(fn ($t) => $t->abbreviation(), array_slice($out->types(), 1));

    expect(array_keys($expected))->toEqualCanonicalizing(array_keys($types))
        ->and($types)->toEqual($expected)
        ->and(array_unique(array_merge(...array_values(array_slice($out->toColumns(), 1)))))->toBe([null]);
});
