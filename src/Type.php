<?php

declare(strict_types=1);

namespace Phibble;

use InvalidArgumentException;

enum Type: string
{
    case Float = 'float';
    case Int = 'int';
    case Bool = 'bool';
    case String = 'string';
    case Factor = 'factor';
    case Date = 'date';
    case DateTime = 'datetime';
    case Time = 'time';
    case List = 'list';

    /**
     * A Type from a case or its name, such as 'float' or 'date', in any letter case.
     *
     * @throws \InvalidArgumentException For an unknown type name.
     */
    public static function parse(self|string $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        return self::tryFrom(strtolower($type))
            ?? throw new InvalidArgumentException("Unknown column type '{$type}'.");
    }

    /**
     * The short label printed in a column header, as in tibble: dbl, int, lgl, chr, fct, date, dttm, time, or list.
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Float => 'dbl',
            self::Int => 'int',
            self::Bool => 'lgl',
            self::String => 'chr',
            self::Factor => 'fct',
            self::Date => 'date',
            self::DateTime => 'dttm',
            self::Time => 'time',
            self::List => 'list',
        };
    }

    /** Whether the type is numeric: float, int, or bool. */
    public function isNumeric(): bool
    {
        return in_array($this, [self::Float, self::Int, self::Bool], true);
    }
}
