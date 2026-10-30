<?php

declare(strict_types=1);

namespace Phibble\Sql;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use Phibble\Type;

/**
 * SQL differences between SQLite, PostgreSQL and MySQL.
 */
abstract class Dialect
{
    public static function for(PDO $pdo): self
    {
        return self::tryFor($pdo) ?? throw new InvalidArgumentException(
            'SqlTable supports SQLite, PostgreSQL and MySQL, not '.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).'.'
        );
    }

    /** The dialect for a connection, or null for a driver SqlTable does not support. */
    public static function tryFor(PDO $pdo): ?self
    {
        return match ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'sqlite' => new SqliteDialect(),
            'pgsql' => new PostgresDialect(),
            'mysql' => new MysqlDialect(),
            default => null,
        };
    }

    abstract public function name(): string;

    /** A marker standing in for "{}" inside identifiers, so templates don't split on it. */
    public const string BRACES = "\x01";

    public function quote(string $identifier): string
    {
        return '"'.str_replace(['"', '{}'], ['""', self::BRACES], $identifier).'"';
    }

    /** SQL for a double: +Inf, -Inf or NaN. NULL where the database cannot store it. */
    public function special(string $which): string
    {
        return 'NULL';
    }

    /** Truncate a number toward zero and make it an integer, as R's as.integer(). */
    public function truncateToInt(Fragment $x): Fragment
    {
        return Fragment::format('CAST({} AS '.$this->castType('int').')', [$x]);
    }

    /** CAST target for 'int', 'float' and 'string'. */
    abstract public function castType(string $type): string;

    /** @param list<Fragment> $parts */
    public function concat(array $parts): Fragment
    {
        return Fragment::format('('.implode(' || ', array_fill(0, count($parts), '{}')).')', $parts);
    }

    /** 1-based position of $needle in $haystack, 0 if absent. */
    abstract public function position(Fragment $haystack, Fragment $needle): Fragment;

    public function length(Fragment $x): Fragment
    {
        return Fragment::format('CHAR_LENGTH({})', [$x]);
    }

    /** Equality where NULL equals NULL. */
    abstract public function nullSafeEquals(Fragment $a, Fragment $b): Fragment;

    /** The LIMIT clause that means "no limit", for OFFSET-only queries. */
    abstract public function noLimit(): string;

    public function supportsFullJoin(): bool
    {
        return true;
    }

    /** Whether FULL JOIN accepts NULL-safe key equality. */
    public function supportsNullSafeFullJoin(): bool
    {
        return $this->supportsFullJoin();
    }

    /**
     * Sample standard deviation or variance. $aggregate wraps each aggregate
     * call (adding OVER (...) when used as a window).
     *
     * @param \Closure(Fragment): Fragment $aggregate
     */
    public function sampleStat(string $fn, Fragment $x, \Closure $aggregate): Fragment
    {
        return $aggregate(Fragment::format($fn === 'sd' ? 'STDDEV_SAMP({})' : 'VAR_SAMP({})', [$x]));
    }

    /**
     * Text in byte (C) order and case-sensitive, as R compares strings.
     * Wrapped around text columns wherever values are compared, sorted,
     * grouped or joined.
     */
    public function collate(Fragment $x): Fragment
    {
        return $x;
    }

    /**
     * Whether a column is text, from PDOStatement::getColumnMeta().
     *
     * @param array<string, mixed> $meta
     */
    public function isTextColumn(array $meta): bool
    {
        return false;
    }

    /**
     * The type a result column was declared with, from
     * PDOStatement::getColumnMeta(). Null when the driver does not say, and
     * the values are guessed from their text instead.
     *
     * @param array<string, mixed> $meta
     */
    public function columnType(array $meta): ?Type
    {
        return null;
    }

    /**
     * Whether a result column is an exact decimal (NUMERIC, DECIMAL).
     *
     * @param array<string, mixed> $meta
     */
    public function isDecimalColumn(array $meta): bool
    {
        return false;
    }

    /**
     * Types for a result's columns, by position, from the driver's metadata.
     * Positions left out have no declared type, and are guessed.
     *
     * Decimals become doubles, as RPostgres and RMariaDB read them, so digits
     * past a double's 15 or so are lost; whole-number decimals (as from
     * SUM over BIGINT) that fit become integers. Pass a 'string' type to
     * keep a decimal exact.
     *
     * @param list<list<mixed>> $columns The result's values, by position.
     * @return array<int, Type>
     */
    public function resultTypes(PDOStatement $statement, array $columns): array
    {
        $types = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);
            if ($meta === false) {
                continue;
            }
            $type = $this->isDecimalColumn($meta)
                ? (array_all($columns[$i] ?? [], fn ($v) => $v === null || is_int($v) || (is_string($v) && filter_var($v, FILTER_VALIDATE_INT) !== false))
                    ? Type::Int
                    : Type::Float)
                : $this->columnType($meta);
            if ($type !== null) {
                $types[$i] = $type;
            }
        }

        return $types;
    }

    /** A date part as a number: year, month, day, hour, minute, second, yday, dow (0 = Sunday). */
    abstract public function datePart(string $part, Fragment $x): Fragment;

    /** Seconds from $y to $x, as a double. */
    abstract public function secondsBetween(Fragment $x, Fragment $y): Fragment;

    /** Whether UPPER() and LOWER() handle non-ASCII letters. */
    public function unicodeCase(): bool
    {
        return true;
    }

    /** Collapse whitespace runs and trim (str_squish), if the dialect has regex replace. */
    public function squish(Fragment $x): Fragment
    {
        throw new Untranslatable("str_squish() has no {$this->name()} translation.");
    }

    /** Whether LPAD and RPAD exist. */
    public function hasPad(): bool
    {
        return true;
    }

    /** A regular-expression test with stringr's (ICU) semantics, if the dialect has them. */
    public function regexMatch(Fragment $x, string $regex, bool $ignoreCase): Fragment
    {
        throw new Untranslatable("Regular expressions in {$this->name()} differ from stringr's; evaluated in memory.");
    }

    /** A continuous quantile aggregate (type 7), if the dialect has one. */
    public function quantile(Fragment $x, float $p): Fragment
    {
        throw new Untranslatable("quantile() and median() have no {$this->name()} translation.");
    }
}
