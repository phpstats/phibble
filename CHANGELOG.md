# Changelog

All notable changes to phibble are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/). Before 1.0, minor versions may
change the public API.

## [Unreleased]

## [0.6.1] - 2026-11-01

### Changed

- Summaries of dates, datetimes, and times keep R's types. `median()` and
  `quantile()` of a time give a time, as in R, and so do `mean()` and
  `sum()`, where R gives a difftime. They used to give seconds. `mean()` and
  `median()` of a date give a date (rounded down to the day), and `mean()`,
  `median()`, and `quantile()` of a datetime give a datetime. They used to
  give seconds since the epoch. `sum()` of a date or datetime and
  `quantile()` of a date throw, as in R. `sd()`, `var()`, and `iqr()` of a
  date are in days, where they were in seconds. `SqlTable` and `PolarsTable`
  run these summaries in memory.
- `SqlTable` guesses SQLite columns of dates, datetimes, and times from their
  first 1000 rows when it opens a table.

### Fixed

- SQLite text times that are not zero-padded, such as `7:30:00` and
  `10:05:00`, sorted, compared, grouped, and summarized as text. Verbs that
  use them now run in memory, where they order by time.

## [0.6.0] - 2026-11-01

### Added

- A time column type, `Type::Time`, as R's hms. Its values are `Phibble\Time`
  objects, whole microseconds that may be negative or longer than a day, with
  `Time::of()`, `Time::fromSeconds()`, `Time::parse()`, `seconds()`, and
  lubridate's `hour()`, `minute()`, and `second()`.
- CSV reading guesses and parses times as readr does, such as `7:30`,
  `00:07:15`, and `1:30 PM`, with the PHP and phit readers alike. The `t`
  and `time` column types read times. `writeCsv()` writes them as readr
  does.
- Times print and `glimpse()` as tibble shows hms, convert to and from
  numbers (seconds), text, and datetimes as R does, and compare, sort, group,
  and join. Arithmetic with seconds gives a time.
- PostgreSQL `time` and MySQL `TIME` columns read as times. Comparisons of
  them compile to SQL, and other functions of them run in memory.
  `PolarsTable` holds times as Polars durations.

### Changed

- SQLite text that looks like times, such as `07:30:00`, is guessed as a time
  column, as readr would guess it.

## [0.5.3] - 2026-11-01

### Added

- `fromCsv()`, `fromCsvChunked()`, and `csvChunks()` read `http://` and
  `https://` URLs, as readr does. The file is downloaded to a temporary file,
  read as a local file by the PHP or phit reader, and deleted. A URL whose
  path ends in `.gz` is decompressed. Reading a URL needs `allow_url_fopen`,
  and a failed download names the URL and the reason.

## [0.5.2] - 2026-11-01

### Changed

- The PHP CSV reader packs a column whose values are mostly distinct into
  one string per 4096 rows while it reads, and parses numbers and dates a
  block at a time. Reading a 2M-row, 7-column file peaks at 239 MB of PHP
  heap instead of 378 MB.
- `filter()` and `mutate()` on an ungrouped table no longer build a list of
  every row number.
- Grouping numbers each key column's distinct values and combines the
  numbers, instead of building a key string for every row.
- A string function of a column, such as `str_sub()`, computes each
  repeated value once and shares repeated results, as the CSV reader shares
  repeated values. Both stop when the values are mostly distinct.
- On 2M rows, a read, filter, mutate, and grouped summary peaks at 513 MiB
  RSS instead of 634 MiB and runs in 2.5 s instead of 3.3 s. With the phit
  reader it runs in 1.1 s instead of 2.1 s. Results are unchanged.

## [0.5.1] - 2026-10-31

### Added

- `PolarsTable::summarize()`, an alias of `summarise()`, as `DataFrame` and
  `SqlTable` have.

### Changed

- `composer.json` allows PHP 8.3, 8.4, and 8.5 (`>=8.3 <8.6`), the versions
  CI tests. It used to allow any later 8.x release.
- `composer.json` requires the mbstring extension, which printing and the
  string functions use.
- The documentation is at https://phibble.phpstats.org/docs/.
  https://phpstats.github.io/phibble/ redirects there.

### Fixed

- A `summarize()` aggregate that is NA in every group has the type dplyr
  gives it. `mean()`, `sd()`, `var()`, `quantile()`, and `iqr()` are
  doubles. `sum()` of ints or bools is an int, and `median()` of ints is an
  int. `first()`, `last()`, and `nth()` keep their column's type, and so do
  `min()` and `max()`, except that they give ints for bools. These columns
  were bool before.
- `iqr(x, na_rm: true)` of no values is NA, as in R. It returned 0.
- A row with more fields than columns, split on a space or tab delimiter,
  now has its joined last field trimmed, as readr does. 0.5.0 skipped the
  trim when no single field had a space or tab at its edge, so
  `1  x  y  ` read with a space delimiter gave ` x  y  ` instead of `x  y`.

## [0.5.0] - 2026-10-30

### Added

- `DataFrame::fromCsvChunked()` and `DataFrame::csvChunks()` read delimited
  text a chunk of rows at a time, as readr's `read_delim_chunked()` does.
  Types are guessed from the first chunk and kept for the rest. A 105 MB file
  reads in 10,000-row chunks within 16 MB.
- With phit 0.2.0 or later loaded, the chunked readers use its
  `Phit\CsvReader`, about three times faster on a wide tab-separated file,
  with the same results as the PHP reader.

### Changed

- The CSV reader reads its input in 4 MB blocks instead of all at once, so
  `fromCsv()` no longer holds the file's text alongside its rows. A CR at the
  end of a block waits for the next one, and the first 4 MB decide whether
  lines end in CR alone, so results do not depend on the block size.
- The PHP CSV reader trims only blocks that have a space or tab at a field's
  edge, and finds NA strings with a hash lookup.

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

[Unreleased]: https://github.com/phpstats/phibble/compare/v0.6.1...HEAD
[0.6.1]: https://github.com/phpstats/phibble/releases/tag/v0.6.1
[0.6.0]: https://github.com/phpstats/phibble/releases/tag/v0.6.0
[0.5.3]: https://github.com/phpstats/phibble/releases/tag/v0.5.3
[0.5.2]: https://github.com/phpstats/phibble/releases/tag/v0.5.2
[0.5.1]: https://github.com/phpstats/phibble/releases/tag/v0.5.1
[0.5.0]: https://github.com/phpstats/phibble/releases/tag/v0.5.0
[0.4.1]: https://github.com/phpstats/phibble/releases/tag/v0.4.1
[0.4.0]: https://github.com/phpstats/phibble/releases/tag/v0.4.0
[0.3.0]: https://github.com/phpstats/phibble/releases/tag/v0.3.0
[0.2.0]: https://github.com/phpstats/phibble/releases/tag/v0.2.0
[0.1.0]: https://github.com/phpstats/phibble/releases/tag/v0.1.0
