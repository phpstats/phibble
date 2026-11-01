<?php

declare(strict_types=1);

namespace Phibble\Expr;

use InvalidArgumentException;

/**
 * A lubridate period such as months(1), added to or subtracted from dates
 * and datetimes by clock arithmetic.
 *
 * @internal
 */
final readonly class Period
{
    public const array UNITS = ['seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years'];

    public function __construct(public int|float $amount, public string $unit)
    {
        self::check($amount, $unit);
    }

    /**
     * lubridate's rule: a period of seconds may be fractional; every other
     * unit needs a whole number.
     *
     * @throws InvalidArgumentException
     */
    public static function check(mixed $amount, string $unit): void
    {
        if ($unit !== 'seconds' && is_float($amount) && is_finite($amount) && $amount !== \floor($amount)) {
            throw new InvalidArgumentException("{$unit}() needs a whole number, got {$amount}; only seconds() takes a fraction, as in lubridate.");
        }
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->unit);
    }
}
