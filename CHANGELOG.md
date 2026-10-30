# Changelog

All notable changes to phibble are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/). Before 1.0, minor versions may
change the public API.

## [0.4.1] - 2026-10-30

### Fixed

- `fromCsv(select: [...])` no longer holds or parses the columns outside
  `select`, as readr's `col_select` does. Those columns raised parsing
  warnings and cost time and memory. Rows with the wrong number of fields
  still warn.
- Quoted CSV fields follow readr 2's rules. Inside a field that starts with a
  quote, a lone quote toggles quoting and is dropped, so `"x "y" z"` reads as
  `x y z` and `"x "y",z"` as `x y,z`. Before, the field ended at the first lone
  quote and the rest was read literally. With `escapeDouble: false`, inner
  quotes are kept, as in readr.
- A quoted field left open at the end of the file is reported as a problem.
- Problems name rows as `readr::problems()` does. Skipped, blank, and comment
  lines no longer shift the numbers.

### Changed

- `writeCsv()` writes a chunk of rows at a time. On a 100,000-row,
  17.6 MB table its peak memory fell from 161 MB to 3 MB, and the output is
  unchanged.
- The CSV and PDO readers share one string among a column's repeated
  values, and stop pooling in a column whose values are mostly distinct.
  `fromPdo()` no longer copies each column while typing it and releases
  each raw column once typed. On the same table, `fromCsv()` holds 23 MB
  instead of 40 MB and peaks at 56 MB instead of 79 MB; `fromPdo()` holds
  24 MB instead of 41 MB and peaks at 75 MB instead of 114 MB.
- Joins keep no per-row list of matches, and a single whole-number key is
  used as the array key itself. A 100,000-row left join on an integer key
  peaks at 36 MB instead of 95 MB and runs in a third of the time.
- A named `col_types` entry that matches no column raises an
  `E_USER_WARNING` and the file is read, as readr's `cols()` does. It used to
  throw.
- The native CSV reader is used only with phit newer than 0.1.0. phit 0.1.0
  reads stray quotes the old way, so phibble reads CSV in PHP when it is
  loaded.

## [0.4.0] - 2026-10-30

### Changed

- The package is now `phpstats/phibble`, in the phpstats GitHub organization.
  The docs are at https://phpstats.github.io/phibble/. PHP namespaces are
  unchanged.
- php-stats is now phit. The native CSV reader calls `Phit\read_csv()` from
  the phit extension (`ext-phit`), and the docs link to
  [phit](https://github.com/phpstats/phit). `stats_read_csv()` from the
  php-stats extension is no longer used.
- `fromPdo()`, `fromQuery()`, the Laravel `->toDataFrame()` macro, and
  `SqlTable` type PostgreSQL and MySQL columns from their declared types.
  Text columns stay text, so `'TRUE'` and `'2024-01-02'` in a `TEXT` column
  are strings, and `'2024-02-30'` no longer becomes NA. Empty results keep
  their column types. SQLite results are still guessed from their values.
- `NUMERIC` and `DECIMAL` columns are doubles, or integers when every value
  is a whole number. Digits past a double's precision are lost; pass a
  `string` type to keep a decimal exact.
- `tbl()` throws when it gets bindings with a table name. It used to drop
  them.
- Docblocks, an error message, and test names say `DataFrame` or table
  instead of the bare "frame". The bench backend `frame` is now `dataframe`.

### Added

- `PolarsTable::$strict`. When it is on, a verb that would run in an
  in-memory DataFrame throws `PolarsFallback`, naming the verb and the
  reason.

### Fixed

- The docs call the visualization library phiz again. 0.3.0 renamed it to
  phit by mistake.
- PostgreSQL `timestamptz` values with an hour-only offset, such as
  `2024-01-02 03:04:05-05`, parse as datetimes. `"Infinity"` and
  `"-Infinity"` convert to numbers, as in R's `as.numeric()`.

## [0.3.0] - 2026-10-17

### Changed

- phibble now supports PHP 8.3 and 8.4 as well as 8.5. CI runs on all three
  against PostgreSQL 17 and MySQL 8. `symfony/polyfill-php84` provides
  `array_any()` and `array_all()` before PHP 8.4.
- On PHP 8.3, `SqlTable` reads PostgreSQL float columns as floats, including
  `Infinity` and `NaN`, as PHP 8.4's driver does.
- The docs show pipelines as method chains (PHP 8.3+) and with `|>` (PHP 8.5), including phplyr's `phplyr($t)->...->get()` chain.

## [0.2.0] - 2026-10-17

### Changed

- `Phibble\Frame` is now `Phibble\DataFrame`. The old name is removed. The
  Laravel macros are now `->toDataFrame()` on collections and query builders.

### Added

- The Polars backend, `Phibble\Polars\PolarsTable`, moved here from the
  phpolars-phibble package. It implements the Table contract on Polars through
  the php_polars extension, which is suggested and not required. `polars()`
  creates one from any table.
- A Backends documentation page: `DataFrame`, `SqlTable`, and `PolarsTable` as
  interchangeable implementations of `Phibble\Contracts\Table`.
- `PolarsTable::fromTable()` throws for list-columns that Polars would store
  differently, such as named arrays with different keys.

## [0.1.0] - 2026-10-16

The first release. phibble is a PHP 8.5 port of R's tibble, with readr-style
input and output, and the shared table type for phplyr, phpolars, php-stats,
and cmdstanphp.

### Frames and types

- `Frame`, an immutable, typed, in-memory data frame. NA is `null`.
- Column types: float, int, bool, string, factor (with ordered factors), date,
  datetime, and list-columns. Dates and datetimes are stored as integers.
- Type inference following R's `c()`, and casts following R's `as.*()`, with
  a warning when values become NA.
- forcats basics: `fctRelevel`, `fctLevels`, `fctRev`, `fctInorder`,
  `fctInfreq`, `fctReorder`, `fctRecode`, `fctCollapse`, and `fctDrop`.

### tibble

- `tibble()`, `tribble()`, `as_tibble()`, `enframe()`, `deframe()`,
  `addRow()`, and `addColumn()`, with tibble's recycling rules and messages.
- Name repair as in vctrs: `check_unique`, `unique`, `universal`, and
  `minimal`.
- Printing and `glimpse()` that match tibble's output, checked against R.
- Strict column access through `$frame->x` and `$frame['x']`.

### Verbs and the Table contract

- `Phibble\Contracts\Table`, the interface that `Frame`, `SqlTable`, and
  phpolars' `PolarsTable` implement.
- `select`, `filter`, `mutate`, `rename`, `arrange`, `groupBy` (with
  `drop:`), `rowwise`, `summarise` (with `.groups`), `distinct`, `slice`,
  `head`, `take`, `eachGroup`, `groupRows`, `mutateGroups`, and
  `filterGroups`, with dplyr 1.1 semantics and dplyr's group order.
- Joins with dplyr's semantics, including `join_by` comparisons, rolling
  (`closest`) joins, `keep`, `naMatches`, `multiple`, `relationship`, and
  `unmatched`.
- `bindCols`, `concat`, `nest`, `unnest`, and tidyr's spec-based pivots
  (`pivotLongerSpec`, `pivotWiderSpec`).

### Expressions

- `Phibble\Expr`: inspectable, serializable expressions with dplyr's
  semantics, accepted by `filter`, `mutate`, `summarise`, and `arrange`.
- A fixed registry of functions: operators, conditionals, math, aggregates,
  window functions, stringr basics (regular expressions by default, with
  `fixed()` and `regex()`), and lubridate basics (parsing, parts, rounding,
  periods, `difftime`, and time zones).

### SQL tables

- `SqlTable` and `tbl()`: lazy tables over PDO for SQLite, PostgreSQL, and
  MySQL. Verbs with expressions compile to SQL; anything without an exact SQL
  translation runs in memory, so results match `Frame`.
- `showQuery()` and `bindings()`, source row order, and native joins
  including `join_by` comparisons and rolling joins.

### Input and output

- CSV reading with readr's options and type guessing, and `writeCsv()` /
  `toCsv()` as `write_csv()` writes.
- The php-stats extension's native CSV reader, when it is loaded.
- JSON in records or columns form, PDO queries, Laravel and Doctrine query
  builders, and Laravel collections.
- `toModelData()` for php-stats' model functions.

### Laravel

- An auto-discovered service provider with `->toFrame()` on collections and
  query builders and `->phibble()` for lazy SQL tables. Global scopes such as
  soft deletes apply.

[0.4.1]: https://github.com/phpstats/phibble/releases/tag/v0.4.1
[0.4.0]: https://github.com/phpstats/phibble/releases/tag/v0.4.0
[0.3.0]: https://github.com/phpstats/phibble/releases/tag/v0.3.0
[0.2.0]: https://github.com/phpstats/phibble/releases/tag/v0.2.0
[0.1.0]: https://github.com/phpstats/phibble/releases/tag/v0.1.0
