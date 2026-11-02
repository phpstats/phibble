# phibble

phibble is a data frame library for PHP, following R's tibble and readr. A
`DataFrame` holds typed columns, treats `null` as NA, and returns a new table
from every operation. It is the shared table type for
[phplyr](https://github.com/phpstats/phplyr),
[phit](https://github.com/phpstats/phit),
[phpolars](https://github.com/phpstats/phpolars),
[phiz](https://github.com/phpstats/phiz), and
[CmdStanPHP](https://github.com/phpstats/CmdStanPHP).

Documentation: <https://phibble.phpstats.org/docs>

## Installation

```bash
composer require phpstats/phibble
```

phibble supports PHP 8.3, 8.4, and 8.5, and CI tests all three. It requires
the mbstring and PDO extensions. On PHP 8.5, pipelines can also use the `|>`
operator.

## Example

In a new directory, install phibble:

```bash
composer require phpstats/phibble
```

Then save this script as `summary.php` and run `php summary.php`. It reads the
Palmer penguins data from the
[PHPstats datasets](https://github.com/phpstats/datasets#penguins):

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Phibble\DataFrame;

use function Phibble\Expr\{col, mean, n};

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/main/csv/penguins.csv'
);

echo $penguins
    ->filter(col('body_mass_g')->isNa()->not())
    ->groupBy(['species'])
    ->summarize(['n' => n(), 'mass' => mean(col('body_mass_g'))]), "\n";
```

```
# A tibble: 3 × 3
  species       n  mass
  <chr>     <int> <dbl>
1 Adelie      151 3701.
2 Chinstrap    68 3733.
3 Gentoo      123 5076.
```

## Features

- **tibble:** `tibble()`, `tribble()`, `as_tibble()`, `addRow()`,
  `addColumn()`, `enframe()`, `deframe()`, name repair, strict column access,
  and printing and `glimpse()` that match tibble's output.
- **Types:** float, int, bool, string, factor (with ordered factors and the
  forcats functions), date, datetime, time, and list-columns. Casts follow R's
  `as.*()` rules.
- **Input and output:** CSV with readr's options and type guessing (and the
  phit extension's native reader when it is loaded), JSON, PDO queries,
  Laravel and Doctrine query builders, and Laravel collections.
- **Table methods:** `select`, `filter`, `mutate`, `arrange`, `groupBy`,
  `summarize`, `distinct`, joins (including `join_by` comparisons and rolling
  joins), `nest`, `unnest`, and tidyr's spec-based pivots, with dplyr 1.1
  semantics.
- **Expressions:** `Phibble\Expr` describes computations as inspectable,
  serializable trees that follow dplyr's semantics, with stringr and
  lubridate basics.
- **SQL tables:** `SqlTable` compiles table methods to SQL for SQLite,
  PostgreSQL, and MySQL, like dbplyr. Anything SQL cannot compute exactly runs
  in memory.
- **Laravel:** `->toDataFrame()` and `->phibble()` on queries and collections.
- **Backends:** `DataFrame` (in memory), `SqlTable` (databases), and
  `PolarsTable` (Polars, with the php_polars extension from phpolars) are all
  part of phibble and implement one Table contract, so the same pipeline runs
  on each.

## Development

```bash
composer install
vendor/bin/pest
```

See the [development guide](https://phibble.phpstats.org/docs/development.html)
for the R comparison scripts, database tests, and documentation build.

## License

phibble is released under the [MIT license](LICENSE).
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) lists the code that follows
other projects' source, with their licenses.
