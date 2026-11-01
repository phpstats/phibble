<?php

declare(strict_types=1);

namespace Phibble;

use InvalidArgumentException;
use JsonSerializable;
use Phibble\Format\TimeFormat;
use Stringable;

/**
 * A time of day, as R's hms: a number of microseconds, which may be negative
 * or past 24 hours, as an hms duration may. Time columns return these.
 */
final readonly class Time implements JsonSerializable, Stringable
{
    private const int MICRO = 1_000_000;

    public function __construct(public int $microseconds) {}

    /** A time from hours, minutes, and seconds, as hms::hms(). */
    public static function of(int $hours, int $minutes = 0, int|float $seconds = 0): self
    {
        return self::fromSeconds($hours * 3600 + $minutes * 60 + $seconds);
    }

    /** A time from a number of seconds, rounded to the microsecond. */
    public static function fromSeconds(int|float $seconds): self
    {
        if (is_float($seconds) && ! is_finite($seconds)) {
            throw new InvalidArgumentException('A time needs a finite number of seconds.');
        }

        return new self(is_int($seconds) ? $seconds * self::MICRO : (int) round($seconds * self::MICRO));
    }

    /**
     * Text such as "07:30", "7:30:15.25", "-01:00:00", "100:00:00", or
     * "1:30 PM" as a time, or null when it is not one. Unlike reading CSV,
     * which follows readr, fractional seconds are kept.
     */
    public static function parse(string $text): ?self
    {
        if (! preg_match('/^\s*(-)?(\d+):(\d{2})(?::(\d{1,2})(\.\d*)?)?\s*([AaPp][Mm])?\s*$/', $text, $m)) {
            return null;
        }
        [$hours, $minutes, $seconds] = [(int) $m[2], (int) $m[3], (int) (($m[4] ?? '') ?: 0)];
        if ($minutes > 59 || $seconds > 59) {
            return null;
        }
        $hours = self::meridiem($hours, $m[6] ?? '');
        $fraction = ($m[5] ?? '') === '' ? 0 : (int) str_pad(substr($m[5], 1, 6), 6, '0');
        $micro = (($hours * 60 + $minutes) * 60 + $seconds) * self::MICRO + $fraction;

        return new self(($m[1] ?? '') === '-' ? -$micro : $micro);
    }

    /** readr's AM/PM rule: 12 AM is hour 0, and PM adds 12 hours to any hour but 12. */
    public static function meridiem(int $hours, string $suffix): int
    {
        return match (strtolower($suffix)) {
            'am' => $hours === 12 ? 0 : $hours,
            'pm' => $hours === 12 ? 12 : $hours + 12,
            default => $hours,
        };
    }

    /** The number of seconds, as R's as.numeric() of an hms. */
    public function seconds(): float
    {
        return $this->microseconds / self::MICRO;
    }

    /** The hour of the day, 0 to 23, as lubridate::hour(). */
    public function hour(): int
    {
        return intdiv($this->ofDay(), 3600 * self::MICRO);
    }

    /** The minute of the hour, as lubridate::minute(). */
    public function minute(): int
    {
        return intdiv($this->ofDay() % (3600 * self::MICRO), 60 * self::MICRO);
    }

    /** The second of the minute, with its fraction, as lubridate::second(). */
    public function second(): float
    {
        return ($this->ofDay() % (60 * self::MICRO)) / self::MICRO;
    }

    /** The time as hms prints one value, such as "07:30:00" or "25:01:01.25". */
    public function __toString(): string
    {
        return TimeFormat::hms([$this->microseconds])[0];
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** Microseconds since the start of the day, as the clock shows it. */
    private function ofDay(): int
    {
        $day = 86400 * self::MICRO;

        return (($this->microseconds % $day) + $day) % $day;
    }
}
