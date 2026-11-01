<?php

declare(strict_types=1);

namespace Phibble\Expr;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The clock behind today() and now(). Tests can freeze it.
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    /** Fix the time that today() and now() read. Null restores the system clock. */
    public static function freeze(?DateTimeImmutable $at): void
    {
        self::$frozen = $at;
    }

    /** The current time in UTC, or the frozen time. */
    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
