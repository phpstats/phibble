<?php

declare(strict_types=1);

namespace Phibble\Expr;

use DateTimeInterface;
use InvalidArgumentException;
use Phibble\Time;

/**
 * A literal: null, bool, int, float, string, a date, datetime, or time, or a
 * list (only as the second argument of `in`) of scalars or of temporal values
 * of one kind.
 */
final readonly class Lit extends Expr
{
    public mixed $value;

    public ?string $dtype;

    public function __construct(mixed $value, ?string $dtype = null)
    {
        if (is_array($value)) {
            $value = array_values($value);
            $dtype ??= self::listType($value);
        } elseif ($value instanceof DateTimeInterface) {
            $dtype ??= $value->format('H:i:s.u') === '00:00:00.000000' ? 'date' : 'datetime';
        } elseif ($value instanceof Time) {
            $dtype = 'time';
        } elseif (! ($value === null || is_scalar($value))) {
            throw new InvalidArgumentException('Literals must be scalars, dates, times, or lists of scalars, got '.get_debug_type($value).'.');
        }
        if (! in_array($dtype, [null, 'date', 'datetime', 'time'], true)) {
            throw new InvalidArgumentException("Unknown literal dtype '{$dtype}'.");
        }
        $this->value = $value;
        $this->dtype = $dtype;
    }

    /**
     * The dtype of a list: null for scalars, or date, datetime, or time when
     * it holds temporal values, which must all be of one kind.
     *
     * @param list<mixed> $values
     */
    private static function listType(array $values): ?string
    {
        $kinds = [];
        foreach ($values as $v) {
            $kinds[] = match (true) {
                $v === null => null,
                $v instanceof Time => 'time',
                $v instanceof DateTimeInterface => $v->format('H:i:s.u') === '00:00:00.000000' ? 'date' : 'datetime',
                is_scalar($v) => 'scalar',
                default => throw new InvalidArgumentException('List literals may only hold scalars, dates, datetimes, or times, got '.get_debug_type($v).'.'),
            };
        }
        $kinds = array_values(array_unique(array_filter($kinds, fn ($k) => $k !== null)));
        // A list of dates with some at a time of day is a list of datetimes.
        if (array_diff($kinds, ['date', 'datetime']) === [] && count($kinds) === 2) {
            return 'datetime';
        }
        if (count($kinds) > 1) {
            throw new InvalidArgumentException('List literals may not mix '.implode(' and ', array_map(fn ($k) => $k === 'scalar' ? 'scalars' : "{$k}s", $kinds)).'.');
        }

        return $kinds === [] || $kinds[0] === 'scalar' ? null : $kinds[0];
    }

    /** @param array<string, mixed> $node */
    public static function fromArray(array $node): self
    {
        $dtype = $node['dtype'] ?? null;
        $value = $node['value'] ?? null;
        $read = function (mixed $v) use ($dtype) {
            if ($dtype === 'time' && is_string($v)) {
                return Time::parse($v) ?? throw new InvalidArgumentException("Invalid time literal '{$v}'.");
            }

            return $dtype !== null && is_string($v) ? new \DateTimeImmutable($v, new \DateTimeZone('UTC')) : $v;
        };

        return new self(is_array($value) ? array_map($read, $value) : $read($value), $dtype);
    }

    public function toArray(): array
    {
        $write = fn (mixed $v) => match (true) {
            $v instanceof DateTimeInterface => $this->dtype === 'date' ? $v->format('Y-m-d') : $v->format('Y-m-d\TH:i:s.uP'),
            $v instanceof Time => (string) $v,
            default => $v,
        };
        $value = is_array($this->value) ? array_map($write, $this->value) : $write($this->value);

        return $this->dtype === null
            ? ['type' => 'lit', 'value' => $value]
            : ['type' => 'lit', 'value' => $value, 'dtype' => $this->dtype];
    }

    public function columns(): array
    {
        return [];
    }
}
