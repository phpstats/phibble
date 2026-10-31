<?php

declare(strict_types=1);

namespace Phibble\Io;

use Closure;
use Generator;
use InvalidArgumentException;
use Phibble\Column\ArrayColumn;
use Phibble\Column\Column;
use Phibble\Column\TemporalColumn;
use Phibble\DataFrame;
use Phibble\NameRepair;
use Phibble\Source\CsvSource;
use Phibble\Type;

/**
 * Reads CSV files with the phit extension's Phit\read_csv() when it
 * is loaded, which parses and types columns natively. Name repair,
 * col_select, factor and number types, and the problems warning stay in
 * PHP, so results match the PHP reader.
 *
 * Set PHIBBLE_NATIVE_CSV=0 or call NativeCsv::enable(false) to always use
 * the PHP reader.
 *
 * @internal
 */
final class NativeCsv
{
    private static bool $enabled = true;

    /** @var (Closure(string, array): array)|null A stand-in reader, for tests. */
    private static ?Closure $reader = null;

    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Use another implementation of Phit\read_csv(), or null for the
     * extension's.
     *
     * @param (Closure(string, array): array)|null $reader
     */
    public static function using(?Closure $reader): void
    {
        self::$reader = $reader;
    }

    public static function available(): bool
    {
        return self::$enabled
            && getenv('PHIBBLE_NATIVE_CSV') !== '0'
            && (self::$reader !== null || self::extensionAgrees());
    }

    /**
     * Whether the phit extension is loaded and reads quotes as vroom does.
     * phit 0.1.0 ends a quoted field at its first lone quote, where vroom
     * toggles quoting, so phibble reads CSV in PHP with it.
     */
    public static function extensionAgrees(): bool
    {
        return function_exists('Phit\\read_csv') && version_compare((string) phpversion('phit'), '0.1.0', '>');
    }

    /** Whether these options can be handed to the native reader. */
    public static function supports(string $path, CsvOptions $o): bool
    {
        return strlen($o->delimiter) === 1
            && strlen($o->quote) <= 1
            && ! str_ends_with(strtolower($path), '.gz');
    }

    /** Whether phit can read in chunks (Phit\CsvReader, from phit 0.2.0). */
    public static function chunksAvailable(): bool
    {
        return self::$enabled
            && getenv('PHIBBLE_NATIVE_CSV') !== '0'
            && self::$reader === null
            && class_exists('Phit\\CsvReader');
    }

    /**
     * @return array{0: DataFrame, 1: list<string>} The DataFrame and its problems.
     */
    public static function read(string $path, CsvOptions $o): array
    {
        $base = self::options($o);

        // Column names first (a header-only read), to resolve col_types by name.
        $probe = self::call($path, [...$base, 'n_max' => 0]);
        $names = self::names($probe['names'], $o);
        $specs = ColTypes::resolve($o->types, $names, $o->select);

        $result = self::call($path, [...$base, 'n_max' => $o->nMax, 'types' => self::codes($specs)]);
        [$df, $problems] = self::finish($result, $names, $specs, $o);

        return [$df, [...array_values($result['problems'] ?? []), ...$problems]];
    }

    /**
     * The chunks of CsvSource::chunks(), read by Phit\CsvReader: positions,
     * types fixed from the first chunk, and problems per chunk, as there.
     *
     * @return Generator<int, DataFrame>
     */
    public static function chunks(string $path, CsvOptions $o, int $size): Generator
    {
        $base = self::options($o);
        $names = self::names((new \Phit\CsvReader($path, $base))->names(), $o);
        if ($names === []) {
            return;
        }
        $specs = ColTypes::resolve($o->types, $names, $o->select);
        $reader = new \Phit\CsvReader($path, [...$base, 'n_max' => $o->nMax, 'types' => self::codes($specs)]);
        while (($chunk = $reader->next($size)) !== null) {
            if ($chunk['rows'] === 0) {
                CsvSource::warn(array_values($chunk['problems']));

                return;
            }
            [$df, $problems] = self::finish($chunk, $names, $specs, $o);
            CsvSource::warn([...array_values($chunk['problems']), ...$problems]);

            yield $chunk['position'] => $df;
        }
    }

    /** @return array<string, mixed> The options Phit\read_csv() and Phit\CsvReader take. */
    private static function options(CsvOptions $o): array
    {
        return [
            'delimiter' => $o->delimiter,
            'quote' => $o->quote,
            'escape_double' => $o->escapeDouble,
            'escape_backslash' => $o->escapeBackslash,
            'na' => array_values($o->na),
            'trim_ws' => $o->trimWs,
            'skip' => $o->skip,
            'comment' => $o->comment,
            'skip_empty_rows' => $o->skipEmptyRows,
            'col_names' => $o->colNames === true,
            'guess_max' => $o->guessMax,
            'decimal_mark' => $o->decimalMark,
            'grouping_mark' => $o->groupingMark,
        ];
    }

    /**
     * phit's type codes. Factors and readr's "number" are read as text and
     * parsed in PHP.
     *
     * @param list<array{type: Type, number: bool, levels: ?list<string>, ordered: bool}|'skip'|null> $specs
     * @return list<string>
     */
    private static function codes(array $specs): array
    {
        return array_map(fn ($spec) => match (true) {
            $spec === null => '?',
            $spec === 'skip' => '_',
            $spec['number'], $spec['type'] === Type::Factor => 'c',
            default => match ($spec['type']) {
                Type::Bool => 'l',
                Type::Int => 'i',
                Type::Float => 'd',
                Type::Date => 'D',
                Type::DateTime => 'T',
                default => 'c',
            },
        }, $specs);
    }

    /**
     * A native result as a DataFrame, with the problems found finishing it:
     * factors and numbers parsed in PHP, select applied, and each column's
     * failed parses reported.
     *
     * @param array{columns: list<array{type: string, values: list<mixed>, failed: int}|null>} $result
     * @param list<string> $names
     * @param list<array{type: Type, number: bool, levels: ?list<string>, ordered: bool}|'skip'|null> $specs
     * @return array{0: DataFrame, 1: list<string>}
     */
    private static function finish(array $result, array $names, array $specs, CsvOptions $o): array
    {
        $problems = [];
        $parser = new TextParser($o->decimalMark, $o->groupingMark);
        $data = [];
        foreach ($names as $i => $name) {
            $spec = $specs[$i];
            $native = $result['columns'][$i] ?? null;
            if ($spec === 'skip' || $native === null) {
                continue;
            }
            $values = $native['values'];
            $failed = (int) ($native['failed'] ?? 0);
            if ($spec !== null && ($spec['number'] || $spec['type'] === Type::Factor)) {
                // Requested as text; finish the parse in PHP.
                [$column, $failed] = $parser->parse($values, $spec['type'], [
                    'grouping' => false,
                    'number' => $spec['number'],
                    'levels' => $spec['levels'],
                    'ordered' => $spec['ordered'],
                ]);
                $typeName = $spec['type']->value;
            } else {
                $column = self::column($native['type'], $values);
                $typeName = $column->type()->value;
            }
            if ($failed > 0) {
                $problems[] = "column '{$name}': {$failed} value".($failed === 1 ? '' : 's')." could not be parsed as {$typeName}";
            }
            $data[$name] = $column;
        }

        if ($o->select !== null) {
            $selected = [];
            foreach ($o->select as $name) {
                $selected[$name] = $data[$name]
                    ?? throw new InvalidArgumentException("col_select names unknown column '{$name}'.");
            }
            $data = $selected;
        }

        return [DataFrame::fromColumns($data), $problems];
    }

    /** @param list<mixed> $values */
    private static function column(string $type, array $values): Column
    {
        return match ($type) {
            'bool' => new ArrayColumn(Type::Bool, $values),
            'int' => new ArrayColumn(Type::Int, $values),
            'float' => new ArrayColumn(Type::Float, $values),
            'string' => new ArrayColumn(Type::String, $values),
            'date' => new TemporalColumn(Type::Date, $values),
            'datetime' => new TemporalColumn(Type::DateTime, $values),
            default => throw new InvalidArgumentException("Phit\\read_csv() returned unknown column type '{$type}'."),
        };
    }

    /**
     * Names as the PHP reader builds them: given names, X1..Xn for the
     * rest, then name repair.
     *
     * @param list<string> $native
     * @return list<string>
     */
    private static function names(array $native, CsvOptions $o): array
    {
        $width = count($native);
        $names = match (true) {
            $o->colNames === true => array_map(fn (string $n) => $o->trimWs ? trim($n) : $n, $native),
            $o->colNames === false => [],
            default => array_values(array_map(strval(...), $o->colNames)),
        };
        for ($i = count($names); $i < $width; $i++) {
            $names[] = 'X'.($i + 1);
        }

        return NameRepair::repair($names, $o->nameRepair);
    }

    /** @return array{names: list<string>, columns: list<array{type: string, values: list<mixed>, failed: int}|null>, problems: list<string>} */
    private static function call(string $path, array $options): array
    {
        $reader = self::$reader ?? fn (string $path, array $options): array => \Phit\read_csv($path, $options);

        return $reader($path, $options);
    }
}
