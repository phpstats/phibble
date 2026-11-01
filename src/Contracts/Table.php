<?php

declare(strict_types=1);

namespace Phibble\Contracts;

use Closure;
use Phibble\Expr\Expr;
use Phibble\DataFrame;
use Phibble\Type;

/**
 * The table contract every backend implements: the in-memory
 * Phibble\DataFrame, Phibble\Sql\SqlTable, and Phibble\Polars\PolarsTable.
 *
 * Rules shared by every method:
 * - Tables are immutable. Every operation returns a new table. Methods are
 *   declared to return Table: a backend may hand back a different
 *   implementation, e.g. a SQL table falls back to an in-memory DataFrame
 *   for closures it cannot translate. DataFrame returns static.
 * - NA is PHP null.
 * - Grouping is metadata. groupBy() records key columns; methods that use
 *   groups (filter, mutate, summarise) read it, the rest keep it.
 * - Groups are visited in sorted key order, as in dplyr: ascending,
 *   factors by level order, NA last, strings in byte order. Row-level
 *   methods (filter, mutate, arrange) keep the input row order.
 * - A rowwise table (rowwise()) treats every row as its own group, in
 *   row order; groups() returns its id columns.
 * - Row closures receive (Phibble\Row $row, Table $group). $group is the
 *   row's group, or the whole table when ungrouped. Closures that only
 *   need the row can ignore the second argument.
 * - Unknown column names throw Phibble\Exception\ColumnNotFound.
 * - count() is nrow(); iterating yields a Phibble\Row per row.
 *
 * @extends \IteratorAggregate<int, \Phibble\Row>
 */
interface Table extends \Countable, \IteratorAggregate
{
    /**
     * Column names in order.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Column types by name.
     *
     * @return array<string, Type>
     */
    public function types(): array;

    /** Number of rows. SqlTable runs a COUNT(*) query. */
    public function nrow(): int;

    /** Number of columns. */
    public function ncol(): int;

    /**
     * One column's values as a PHP list. Factors return their labels.
     *
     * @return list<mixed>
     * @throws \Phibble\Exception\ColumnNotFound When the column does not exist.
     */
    public function column(string $name): array;

    /**
     * Keep the named columns, in the given order.
     *
     * @param list<string> $names Column names.
     * @throws \Phibble\Exception\ColumnNotFound When a column does not exist.
     */
    public function select(array $names): Table;

    /**
     * Keep rows where the predicate is true. NA counts as false.
     *
     * A closure runs once per row. An Expr runs once per group, a whole column at
     * a time, so aggregates such as mean() compare within the group.
     *
     * @param Closure(\Phibble\Row, Table): ?bool|Expr $predicate A row closure or an expression.
     */
    public function filter(Closure|Expr $predicate): Table;

    /**
     * Keep rows by a predicate that runs once per group.
     *
     * The closure receives the group's table and returns one value per row; a
     * truthy value keeps the row.
     *
     * @param Closure(Table): list<?bool> $predicate
     */
    public function filterGroups(Closure $predicate): Table;

    /**
     * Add or replace columns.
     *
     * Each value is a closure run per row, an Expr evaluated per group (aggregates
     * repeat within the group), a list with one value per row, or a scalar to
     * repeat. Later entries can use earlier ones.
     *
     * @param array<string, Closure(\Phibble\Row, Table): mixed|list<mixed>> $columns New column name => value.
     */
    public function mutate(array $columns): Table;

    /**
     * Add or replace columns with closures that run once per group.
     *
     * Each closure receives the group's table and returns a list with one value
     * per row of the group, or a scalar to repeat. Other values behave as in
     * mutate(). Window functions and across() build on this method.
     *
     * @param array<string, Closure(Table): (list<mixed>|scalar|null)|list<mixed>|scalar|null> $columns New column name => value.
     */
    public function mutateGroups(array $columns): Table;

    /**
     * Rename columns.
     *
     * @param array<string, string> $map New name => old name, as in dplyr.
     * @throws \Phibble\Exception\ColumnNotFound When an old name does not exist.
     */
    public function rename(array $map): Table;

    /**
     * Sort rows. The sort is stable, and NA sorts last in both directions.
     *
     * @param list<string|array{0: string, 1?: 'asc'|'desc'}|Expr> $keys A column name sorts ascending; [name, 'desc'] sorts descending; an Expr sorts by its value, and desc() reverses it.
     */
    public function arrange(array $keys): Table;

    /**
     * Set the grouping columns, replacing any existing grouping.
     *
     * Grouping is metadata: filter(), mutate(), and summarise() work within
     * groups, and other methods keep the grouping.
     *
     * @param list<string> $names Grouping columns.
     * @param bool $drop dplyr's .drop. With false, grouped operations also visit empty groups: every factor level without rows. Other key columns contribute their observed values. The setting survives row operations; ungroup() and groupBy() reset it.
     */
    public function groupBy(array $names, bool $drop = true): Table;

    /** Whether empty groups are dropped. False after groupBy($names, drop: false). */
    public function dropsEmptyGroups(): bool;

    /** Remove the grouping, including rowwise grouping. */
    public function ungroup(): Table;

    /**
     * The grouping columns. Empty when ungrouped; the id columns of a rowwise table.
     *
     * @return list<string>
     */
    public function groups(): array;

    /**
     * Treat each row as its own group, as dplyr's rowwise(). groupBy() and ungroup() clear it.
     *
     * @param list<string> $idColumns Columns summarise() keeps, as with group keys.
     */
    public function rowwise(array $idColumns = []): Table;

    /** Whether the table is rowwise. */
    public function isRowwise(): bool;

    /**
     * Each group's key and rows as an ungrouped table, in sorted key order.
     *
     * Rowwise tables yield one group per row, in row order. An ungrouped table
     * yields one group with an empty key, also when it has no rows.
     *
     * @return iterable<int, array{key: array<string, mixed>, table: Table}>
     */
    public function eachGroup(): iterable;

    /**
     * Each group's key and 0-based row indexes, in the order of eachGroup().
     *
     * Empty groups (drop: false) have no rows; a rowwise table has one group per
     * row.
     *
     * @return list<array{key: array<string, mixed>, rows: list<int>}>
     */
    public function groupRows(): array;

    /**
     * One row per group: the group keys, then one column per entry.
     *
     * Groups are sorted, with NA last. Each entry is a closure given the group's
     * table, or an Expr that evaluates to one value, usually an aggregate. The
     * result drops the last grouping column, as in dplyr; a rowwise table keeps
     * its id columns as groups.
     *
     * @param array<string, Closure(Table): mixed|Expr> $aggregates New column name => closure or expression.
     * @param string|null $groups dplyr's .groups: 'drop_last' (the default), 'drop', 'keep', or 'rowwise'. Rowwise tables default to 'keep'.
     * @throws \LengthException When an expression gives more than one value per group.
     */
    public function summarise(array $aggregates, ?string $groups = null): Table;

    /**
     * Unique rows over the named columns, keeping the first of each and only those columns.
     *
     * @param list<string> $names Columns to compare. An empty list compares all columns.
     */
    public function distinct(array $names = []): Table;

    /**
     * Rows by 0-based position, in the given order. A null index gives an all-NA row.
     *
     * Types, factor levels, and grouping are kept.
     *
     * @param list<int|null> $indices
     */
    public function take(array $indices): Table;

    /**
     * Join another table, with dplyr 1.1 semantics.
     *
     * @param Table $other The right-hand table.
     * @param string $type inner, left, right, full, semi, anti, or cross.
     * @param bool|null $keep Keep both tables' key columns.
     * @param bool $naMatches Whether NA keys match each other.
     * @param string $multiple all, any, first, or last match per x row.
     * @param string|null $relationship one-to-one, one-to-many, many-to-one, or many-to-many; a violation throws.
     * @param string $unmatched drop, or error when the join would drop rows.
     *
     * - $by: list of [leftColumn, rightColumn] pairs; a bare 'k' means
     *   ['k', 'k']. Empty for a cross join.
     * - $type: inner, left, right, full, semi, anti or cross.
     * - Columns: x's columns, then y's non-key columns (all of y's with
     *   $keep). Clashing names get $suffix. Without $keep, key columns come
     *   from x, filled from y for rows only y has (right and full joins).
     * - Rows: x order, with each x row's matches in y order; right and full
     *   joins then add y's unmatched rows in y order. Semi and anti joins
     *   keep x's rows and columns and never duplicate rows.
     * - Matching: NA matches NA unless !$naMatches; 1 matches 1.0; factors
     *   match strings by label.
     * - $multiple: all, any, first or last match per x row.
     * - $relationship: one-to-one, one-to-many, many-to-one or
     *   many-to-many; a violation throws. Unspecified many-to-many warns.
     * - $unmatched: drop, or error when a join would drop rows.
     * - The result keeps x's grouping.
     *
     * @param list<string|array{0: string, 1: string}> $by
     * @param array{0: string, 1: string} $suffix
     */
    public function join(
        Table $other,
        array $by,
        string $type = 'inner',
        array $suffix = ['.x', '.y'],
        ?bool $keep = null,
        bool $naMatches = true,
        string $multiple = 'all',
        ?string $relationship = null,
        string $unmatched = 'drop',
    ): Table;

    /**
     * Pivot columns into rows from a spec, as tidyr's pivot_longer_spec().
     *
     * Each spec row is
     * ['.name' => source column, '.value' => value column, ...name parts].
     * Output: the other (id) columns, the name-part columns, then one column
     * per distinct .value. Each input row gives one row per distinct
     * name-part combination, in spec order; a missing (combination, .value)
     * is NA. $dropNa drops rows whose value columns are all NA. Values
     * combine by vctrs rules (int and double give double); incompatible
     * types throw "Can't combine".
     *
     * @param list<array<string, mixed>> $spec
     */
    public function pivotLongerSpec(array $spec, bool $dropNa = false): Table;

    /**
     * Pivot rows into columns from a spec, as tidyr's pivot_wider_spec().
     *
     * Each spec row is
     * ['.name' => new column, '.value' => values_from column,
     * ...names_from column => value]. New columns appear in spec order after
     * the id columns, which default to every column not used by the spec;
     * rows follow the first appearance of each id combination. Missing
     * cells take $valuesFill[valueColumn] or NA. When some cell has several
     * values, every new column becomes a list-column (a list of values per
     * cell, null when empty) and tidyr's "not uniquely identified" warning
     * is raised.
     *
     * @param list<array<string, mixed>> $spec
     * @param list<string>|null $idColumns
     * @param array<string, mixed> $valuesFill
     */
    public function pivotWiderSpec(array $spec, ?array $idColumns = null, array $valuesFill = []): Table;

    /**
     * Rows from $offset, $length of them or to the end. Ignores groups.
     *
     * @param int $offset 0-based start; negative counts from the end.
     * @param int|null $length Number of rows; null for the rest, negative to stop that many rows before the end.
     */
    public function slice(int $offset, ?int $length = null): Table;

    /** The first $n rows. Ignores groups. */
    public function head(int $n = 6): Table;

    /**
     * The rows as a list of associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function toRows(): array;

    /**
     * The columns as an array of PHP lists, keyed by name.
     *
     * @return array<string, list<mixed>>
     */
    public function toColumns(): array;

    /** The rows as an in-memory DataFrame. SqlTable runs its query; a DataFrame returns itself. */
    public function collect(): DataFrame;
}
