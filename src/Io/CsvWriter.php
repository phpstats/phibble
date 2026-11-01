<?php

declare(strict_types=1);

namespace Phibble\Io;

use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Phibble\DataFrame;
use Phibble\Format\TimeFormat;
use Phibble\Type;

/**
 * Formats a DataFrame as delimited text the way readr::write_delim() does.
 *
 * @internal
 */
final class CsvWriter
{
    /** Rows formatted and written at a time, so the whole output is never held. */
    private const int CHUNK = 2000;

    public static function format(
        DataFrame $df,
        string $delimiter = ',',
        string $na = 'NA',
        bool $colNames = true,
        string $quote = 'needed',
        string $escape = 'double',
        string $eol = "\n",
    ): string {
        $handle = fopen('php://memory', 'r+') ?: throw new \RuntimeException('Cannot open memory stream.');
        self::write($df, $handle, $delimiter, $na, $colNames, $quote, $escape, $eol);
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Write the DataFrame to an open stream, a chunk of rows at a time.
     *
     * @param resource $handle
     */
    public static function write(
        DataFrame $df,
        mixed $handle,
        string $delimiter = ',',
        string $na = 'NA',
        bool $colNames = true,
        string $quote = 'needed',
        string $escape = 'double',
        string $eol = "\n",
    ): void {
        self::check($df, $quote, $escape);
        $types = $df->types();

        $cell = function (?string $text, bool $textual) use ($delimiter, $quote, $escape): string {
            if ($textual) {
                $text = match ($escape) {
                    'double' => str_replace('"', '""', $text),
                    'backslash' => str_replace('"', '\\"', $text),
                    'none' => $text,
                };
            }
            $wrap = match ($quote) {
                'all' => $textual,
                'none' => false,
                'needed' => $text !== '' && strpbrk($text, $delimiter."\"\n\r") !== false,
            };

            return $wrap ? '"'.$text.'"' : $text;
        };

        if ($colNames) {
            self::put($handle, implode($delimiter, array_map(fn (string $n) => $cell($n, true), $df->columns())).$eol);
        }

        // A time column is formatted as a whole, as hms's as.character() is:
        // one number of fraction digits, and hours padded to one width.
        $times = [];
        foreach ($types as $name => $type) {
            if ($type === Type::Time) {
                $times[$name] = TimeFormat::hms($df->storage((string) $name)->ticks());
            }
        }

        $rows = $df->nrow();
        for ($start = 0; $start < $rows; $start += self::CHUNK) {
            $indices = range($start, min($rows, $start + self::CHUNK) - 1);
            $columns = [];
            foreach ($types as $name => $type) {
                $textual = in_array($type, [Type::String, Type::Factor, Type::Date, Type::DateTime, Type::Time], true);
                if (isset($times[$name])) {
                    $columns[] = array_map(
                        fn (?string $text) => $text === null ? $na : $cell($text, true),
                        array_slice($times[$name], $start, count($indices)),
                    );

                    continue;
                }
                $columns[] = array_map(function (mixed $v) use ($type, $textual, $na, $cell): string {
                    $text = self::text($v, $type);

                    return $text === null ? $na : $cell($text, $textual);
                }, $df->storage((string) $name)->take($indices)->values());
            }
            $lines = [];
            foreach (array_keys($indices) as $i) {
                $lines[] = implode($delimiter, array_column($columns, $i));
            }
            self::put($handle, implode($eol, $lines).$eol);
        }
    }

    /** Reject options and columns that cannot be written, before anything is. */
    public static function check(DataFrame $df, string $quote, string $escape): void
    {
        if (! in_array($quote, ['needed', 'all', 'none'], true)) {
            throw new InvalidArgumentException("quote must be 'needed', 'all' or 'none', got '{$quote}'.");
        }
        if (! in_array($escape, ['double', 'backslash', 'none'], true)) {
            throw new InvalidArgumentException("escape must be 'double', 'backslash' or 'none', got '{$escape}'.");
        }
        foreach ($df->types() as $name => $type) {
            if ($type === Type::List) {
                throw new InvalidArgumentException("Column '{$name}' is a list-column; CSV cannot hold it. Unnest or drop it first.");
            }
        }
    }

    /** @param resource $handle */
    private static function put(mixed $handle, string $text): void
    {
        if (fwrite($handle, $text) !== strlen($text)) {
            throw new \RuntimeException('Cannot write CSV output.');
        }
    }

    private static function text(mixed $v, Type $type): ?string
    {
        return match (true) {
            $v === null => null,
            is_bool($v) => $v ? 'TRUE' : 'FALSE',
            is_int($v) => (string) $v,
            is_float($v) => self::formatDouble($v),
            $v instanceof DateTimeInterface => $type === Type::Date
                ? $v->format('Y-m-d')
                : \DateTimeImmutable::createFromInterface($v)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            default => (string) $v,
        };
    }

    /**
     * readr's double formatting: the shortest digits that round-trip, in
     * fixed notation when that stays short, otherwise in readr's
     * scientific forms ("15e14", "1.2e-4"). NaN is NA.
     */
    public static function formatDouble(float $x): ?string
    {
        if (is_nan($x)) {
            return null;
        }
        if (is_infinite($x)) {
            return $x > 0 ? 'Inf' : '-Inf';
        }
        if ($x == 0.0) {
            return '0';
        }

        $sign = $x < 0 ? '-' : '';
        $abs = abs($x);
        for ($p = 1; $p <= 17; $p++) {
            $s = sprintf('%.'.($p - 1).'e', $abs);
            if ((float) $s === $abs) {
                break;
            }
        }
        [$mantissa, $exponent] = explode('e', $s);
        $digits = rtrim(str_replace('.', '', $mantissa), '0') ?: '0';
        $n = strlen($digits);
        $e = (int) $exponent - ($n - 1); // value = digits × 10^e

        if ($e >= 0) {
            return $sign.($e === 0 || $n + $e <= 15 ? $digits.str_repeat('0', $e) : $digits.'e'.$e);
        }

        $after = -$e;
        if ($n > $after) {
            return $sign.substr($digits, 0, $n - $after).'.'.substr($digits, $n - $after);
        }
        $zeros = $after - $n;
        if ($zeros <= 2) {
            return $sign.'0.'.str_repeat('0', $zeros).$digits;
        }

        return $sign.$digits[0].($n > 1 ? '.'.substr($digits, 1) : '').'e'.($e + $n - 1);
    }
}
