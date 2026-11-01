<?php

declare(strict_types=1);

namespace Phibble\Io;

use InvalidArgumentException;
use Phibble\Type;

/**
 * Resolves readr-style col_types into one spec per column.
 *
 * A resolved spec is null (guess), 'skip', or
 * ['type' => Type, 'number' => bool, 'levels' => ?list, 'ordered' => bool].
 *
 * @internal
 */
final class ColTypes
{
    private const array LETTERS = [
        'c' => 'string', 'i' => 'int', 'd' => 'float', 'n' => 'number', 'l' => 'bool',
        'f' => 'factor', 'D' => 'date', 'T' => 'datetime', 't' => 'time', '?' => 'guess',
        '_' => 'skip', '-' => 'skip',
    ];

    private const array ALIASES = [
        'character' => 'string', 'chr' => 'string', 'integer' => 'int', 'double' => 'float',
        'dbl' => 'float', 'numeric' => 'float', 'logical' => 'bool', 'lgl' => 'bool', 'fct' => 'factor',
        'dttm' => 'datetime', 'hms' => 'time', 'number' => 'number', 'guess' => 'guess', 'skip' => 'skip',
    ];

    /**
     * Columns outside $select (col_select) are skipped, so they are never
     * held or parsed, as in readr.
     *
     * @param string|array<int|string, mixed> $types
     * @param list<string> $names
     * @param list<string>|null $select
     * @return list<array{type: Type, number: bool, levels: ?list<string>, ordered: bool}|'skip'|null>
     */
    public static function resolve(string|array $types, array $names, ?array $select = null): array
    {
        $specs = self::specs($types, $names);
        if ($select === null) {
            return $specs;
        }
        foreach ($select as $name) {
            if (! in_array($name, $names, true)) {
                throw new InvalidArgumentException("col_select names unknown column '{$name}'.");
            }
        }

        return array_map(fn ($spec, string $name) => in_array($name, $select, true) ? $spec : 'skip', $specs, $names);
    }

    /**
     * @param string|array<int|string, mixed> $types
     * @param list<string> $names
     * @return list<array{type: Type, number: bool, levels: ?list<string>, ordered: bool}|'skip'|null>
     */
    private static function specs(string|array $types, array $names): array
    {
        if (is_string($types)) {
            if (strlen($types) !== count($names)) {
                throw new InvalidArgumentException(
                    'The compact col_types string has '.strlen($types).' letters; the data has '.count($names).' columns.'
                );
            }

            return array_map(fn (string $letter) => self::spec(self::LETTERS[$letter]
                ?? throw new InvalidArgumentException("Unknown col_types letter '{$letter}'.")), str_split($types));
        }

        if ($types !== [] && array_is_list($types)) {
            if (count($types) !== count($names)) {
                throw new InvalidArgumentException('col_types by position needs one spec per column.');
            }

            return array_map(self::spec(...), $types);
        }

        $default = array_key_exists('.default', $types) ? self::spec($types['.default']) : null;
        unset($types['.default']);
        // readr warns about names that match no column and reads the rest.
        $unknown = array_diff(array_map(strval(...), array_keys($types)), $names);
        if ($unknown !== []) {
            trigger_error("The following named parsers don't match the column names: ".implode(', ', $unknown), E_USER_WARNING);
        }

        return array_map(fn (string $name) => array_key_exists($name, $types) ? self::spec($types[$name]) : $default, $names);
    }

    /** @return array{type: Type, number: bool, levels: ?list<string>, ordered: bool}|'skip'|null */
    private static function spec(mixed $spec): array|string|null
    {
        $levels = null;
        $ordered = false;
        if (is_array($spec)) {
            $levels = isset($spec['levels']) ? array_map(strval(...), $spec['levels']) : null;
            $ordered = (bool) ($spec['ordered'] ?? false);
            $spec = $spec['type'] ?? $spec[0] ?? throw new InvalidArgumentException('A col_types array spec needs a type.');
        }
        if ($spec instanceof Type) {
            return ['type' => $spec, 'number' => false, 'levels' => $levels, 'ordered' => $ordered];
        }

        $name = self::LETTERS[$spec] ?? self::ALIASES[strtolower((string) $spec)] ?? strtolower((string) $spec);

        return match ($name) {
            'guess' => null,
            'skip' => 'skip',
            'number' => ['type' => Type::Float, 'number' => true, 'levels' => null, 'ordered' => false],
            default => ['type' => Type::parse($name), 'number' => false, 'levels' => $levels, 'ordered' => $ordered],
        };
    }
}
