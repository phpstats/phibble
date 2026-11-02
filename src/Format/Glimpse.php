<?php

declare(strict_types=1);

namespace Phibble\Format;

use DateTimeInterface;
use Phibble\Contracts\Table;
use Phibble\Time;
use Phibble\Type;

/**
 * Renders a transposed overview of a table, as tibble's glimpse(): one line
 * per column with its type and as many values as fit the width.
 */
final class Glimpse
{
    public static function render(Table $table, int $width = 80): string
    {
        $df = $table->collect();
        $lines = ['Rows: '.Pillar::bigMark($df->nrow()), 'Columns: '.Pillar::bigMark($df->ncol())];
        if (method_exists($df, 'isRowwise') && $df->isRowwise()) {
            $lines[] = 'Rowwise: '.implode(', ', $df->groups());
        } elseif ($df->groups() !== []) {
            $groups = iterator_count((fn () => yield from $df->eachGroup())());
            $lines[] = 'Groups: '.implode(', ', $df->groups()).' ['.Pillar::bigMark($groups).']';
        }
        if ($df->ncol() === 0) {
            return implode("\n", $lines);
        }

        $head = $df->head(intdiv($width, 3));
        $names = array_map(Pillar::tickIfNeeded(...), $head->columns());
        $nameWidth = max(array_map(Pillar::extent(...), $names));
        foreach ($head->columns() as $i => $name) {
            $prefix = '$ '.Pillar::align($names[$i], $nameWidth).' <'.Pillar::typeAbbr($head, $name).'> ';
            $values = implode(', ', self::formatColumn($head, $name));
            $lines[] = $prefix.Pillar::truncate($values, $width - Pillar::extent($prefix) - 1);
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private static function formatColumn(Table $df, string $name): array
    {
        $values = $df->column($name);

        return match ($df->types()[$name]) {
            Type::Float => self::formatNumbers($values),
            Type::Int => array_map(fn (?int $v) => $v === null ? 'NA' : (string) $v, $values),
            Type::Bool => array_map(fn (?bool $v) => $v === null ? 'NA' : ($v ? 'TRUE' : 'FALSE'), $values),
            Type::String => array_map(fn (?string $v) => $v === null ? 'NA' : self::quote($v), $values),
            Type::Factor => self::formatFactor($df, $name, $values),
            Type::Date => array_map(fn (?DateTimeInterface $v) => $v?->format('Y-m-d') ?? 'NA', $values),
            Type::DateTime => self::formatDateTimes($values),
            Type::Time => self::formatTimes($values),
            Type::List => array_map(self::formatListCell(...), $values),
        };
    }

    /**
     * @param list<?string> $values
     * @return list<string>
     */
    private static function formatFactor(Table $df, string $name, array $values): array
    {
        $levels = method_exists($df, 'levels') ? $df->levels($name) : [];
        $quote = array_any($levels, fn (string $l) => str_contains($l, ','));

        return array_map(fn (?string $v) => $v === null ? 'NA' : ($quote ? self::quote($v) : $v), $values);
    }

    /**
     * R's format() for date-times: dates only when every value is midnight.
     *
     * @param list<?DateTimeInterface> $values
     * @return list<string>
     */
    private static function formatDateTimes(array $values): array
    {
        $midnight = array_all($values, fn (?DateTimeInterface $v) => $v === null || $v->format('H:i:s') === '00:00:00');
        $format = $midnight ? 'Y-m-d' : 'Y-m-d H:i:s';

        return array_map(fn (?DateTimeInterface $v) => $v?->format($format) ?? 'NA', $values);
    }

    /**
     * hms's format(): as.character(), then right-justified with NA.
     *
     * @param list<?Time> $values
     * @return list<string>
     */
    private static function formatTimes(array $values): array
    {
        $texts = array_map(fn (?string $t) => $t ?? 'NA', TimeFormat::hms(array_map(fn (?Time $v) => $v?->microseconds, $values)));
        $width = max(0, ...array_map(strlen(...), $texts ?: ['']));

        return array_map(fn (string $t) => str_pad($t, $width, ' ', STR_PAD_LEFT), $texts);
    }

    private static function formatListCell(mixed $value): string
    {
        if ($value === null) {
            return '<NULL>';
        }
        if ($value instanceof Table) {
            return '[<tbl_df['.$value->nrow().' x '.$value->ncol().']>]';
        }
        if (! is_array($value)) {
            return implode(', ', self::formatAtomic([$value]));
        }

        $atomic = array_all($value, fn ($v) => $v === null || is_scalar($v) || $v instanceof DateTimeInterface)
            && array_is_list($value)
            && Pillar::objSum($value)[1] !== 'list';
        if (! $atomic) {
            return '['.implode(', ', array_map(self::formatListCell(...), array_values($value))).']';
        }
        $formatted = implode(', ', self::formatAtomic($value));

        return count($value) === 1 ? $formatted : "<{$formatted}>";
    }

    /**
     * Format a PHP list the way R formats the equivalent atomic vector.
     *
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function formatAtomic(array $values): array
    {
        $present = array_filter($values, fn ($v) => $v !== null);

        return match (true) {
            array_all($present, fn ($v) => is_bool($v)) => array_map(fn ($v) => $v === null ? 'NA' : ($v ? 'TRUE' : 'FALSE'), $values),
            array_all($present, fn ($v) => is_int($v)) => array_map(fn ($v) => $v === null ? 'NA' : (string) $v, $values),
            array_all($present, fn ($v) => is_int($v) || is_float($v)) => self::formatNumbers($values),
            array_all($present, fn ($v) => $v instanceof DateTimeInterface) => self::formatDateTimes($values),
            default => array_map(fn ($v) => $v === null ? 'NA' : self::quote((string) $v), $values),
        };
    }

    /** R's encodeString(x, quote = '"'). */
    private static function quote(string $s): string
    {
        $escaped = strtr($s, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\t" => '\\t', "\r" => '\\r']);

        return '"'.$escaped.'"';
    }

    /**
     * Doubles as R's format() shows them, with 7 significant digits.
     *
     * @param list<int|float|null> $values
     * @return list<string>
     */
    public static function formatNumbers(array $values): array
    {
        return NumberFormat::format($values);
    }
}
