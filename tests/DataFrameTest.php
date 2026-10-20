<?php

declare(strict_types=1);

use Phibble\Column\FactorColumn;
use Phibble\Contracts\Table;
use Phibble\Exception\ColumnNotFound;
use Phibble\DataFrame;
use Phibble\Row;
use Phibble\Type;

it('builds from rows and round-trips', function () {
    $rows = [['a' => 1, 'b' => 'x'], ['a' => 2, 'b' => 'y']];
    $df = DataFrame::fromRows($rows);

    expect($df)->toBeInstanceOf(Table::class)
        ->and($df->columns())->toBe(['a', 'b'])
        ->and($df->nrow())->toBe(2)
        ->and($df->ncol())->toBe(2)
        ->and(count($df))->toBe(2)
        ->and($df->toRows())->toBe($rows)
        ->and($df->toColumns())->toBe(['a' => [1, 2], 'b' => ['x', 'y']]);
});

it('fills missing keys with NA and keeps first-appearance column order', function () {
    $df = DataFrame::fromRows([['a' => 1], ['b' => 2.5, 'a' => 3]]);

    expect($df->columns())->toBe(['a', 'b'])
        ->and($df->column('b'))->toBe([null, 2.5]);
});

it('builds from columns and rejects ragged columns', function () {
    $df = DataFrame::fromColumns(['x' => [1.0, 2.0], 'y' => [true, false]]);
    expect($df->types())->toBe(['x' => Type::Float, 'y' => Type::Bool]);

    DataFrame::fromColumns(['x' => [1, 2], 'y' => [1]]);
})->throws(InvalidArgumentException::class, "Column 'y' has 1 values; expected 2.");

it('infers types from PHP values', function () {
    $df = DataFrame::fromColumns([
        'f' => [1, 2.5, null],
        'i' => [1, null, 3],
        'b' => [true, false, null],
        's' => ['a', null, 'c'],
        'd' => [new DateTime('2024-01-02'), null, new DateTimeImmutable('2024-03-04')],
        'na' => [null, null, null],
    ]);

    expect($df->types())->toBe([
        'f' => Type::Float, 'i' => Type::Int, 'b' => Type::Bool,
        's' => Type::String, 'd' => Type::DateTime, 'na' => Type::Bool,
    ])
        ->and($df->column('f'))->toBe([1.0, 2.5, null])
        ->and($df->column('d')[0])->toBeInstanceOf(DateTimeImmutable::class);
});

it('accepts explicit types', function () {
    $df = DataFrame::fromColumns(['x' => [1, 2], 'g' => ['b', 'a']], types: ['x' => 'float', 'g' => Type::Factor]);

    expect($df->column('x'))->toBe([1.0, 2.0])
        ->and($df->type('g'))->toBe(Type::Factor)
        ->and($df->levels('g'))->toBe(['a', 'b']);
});

it('turns values it cannot cast into NA with a warning, as R does', function () {
    [$df, $warnings] = quietly(fn () => DataFrame::fromColumns(['x' => ['abc', '2']], types: ['x' => 'int']));

    expect($df->column('x'))->toBe([null, 2])
        ->and($warnings)->toBe(["NAs introduced by coercion to int in column 'x' (1 value)."]);
});

it('throws ColumnNotFound for unknown columns', function () {
    penguins()->column('beak');
})->throws(ColumnNotFound::class, "Column 'beak' not found.");

it('gives rows that read by property and by key', function () {
    $row = penguins()->row(0);

    expect($row)->toBeInstanceOf(Row::class)
        ->and($row->species)->toBe('Adelie')
        ->and($row['mass'])->toBe(3750.0)
        ->and(isset($row->mass))->toBeTrue()
        ->and($row->toArray())->toHaveKeys(['species', 'island', 'mass', 'year']);
});

it('keeps rows read-only', function () {
    $row = penguins()->row(0);
    $row['mass'] = 1;
})->throws(LogicException::class);

it('iterates rows', function () {
    $species = [];
    foreach (penguins() as $i => $row) {
        $species[$i] = $row->species;
    }

    expect($species)->toBe(['Adelie', 'Adelie', 'Gentoo', 'Chinstrap', 'Gentoo']);
});

it('stores factors as levels and codes', function () {
    $df = penguins()->asFactor('species');
    $storage = $df->storage('species');

    expect($storage)->toBeInstanceOf(FactorColumn::class)
        ->and($df->levels('species'))->toBe(['Adelie', 'Chinstrap', 'Gentoo'])
        ->and($storage->codes())->toBe([0, 0, 2, 1, 2])
        ->and($df->column('species'))->toBe(['Adelie', 'Adelie', 'Gentoo', 'Chinstrap', 'Gentoo']);
});

it('uses explicit factor levels and turns unknown values into NA', function () {
    $df = penguins()->asFactor('species', ['Gentoo', 'Adelie']);

    expect($df->levels('species'))->toBe(['Gentoo', 'Adelie'])
        ->and($df->column('species'))->toBe(['Adelie', 'Adelie', 'Gentoo', null, 'Gentoo']);
});

it('sorts numeric factor levels numerically', function () {
    $df = DataFrame::fromColumns(['n' => [10, 9, 100]])->asFactor('n');

    expect($df->levels('n'))->toBe(['9', '10', '100']);
});

it('casts columns', function () {
    $df = penguins()->cast(['year' => 'string', 'mass' => Type::Int]);

    expect($df->column('year'))->toBe(['2007', '2008', '2007', '2009', '2008'])
        ->and($df->column('mass'))->toBe([3750, 3800, 5000, null, 5400]);
});

it('drops rows with NA', function () {
    expect(penguins()->dropNa()->nrow())->toBe(4)
        ->and(penguins()->dropNa(['year'])->nrow())->toBe(5);
});

it('prints like a tibble', function () {
    $out = (string) penguins()->groupBy(['species'])->head(2);

    expect($out)->toBe(<<<'TXT'
        # A tibble: 2 × 4
        # Groups:   species [1]
          species island     mass  year
          <chr>   <chr>     <dbl> <int>
        1 Adelie  Torgersen  3750  2007
        2 Adelie  Biscoe     3800  2008
        TXT);
});
