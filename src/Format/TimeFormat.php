<?php

declare(strict_types=1);

namespace Phibble\Format;

/**
 * Times as the hms package writes them. Values are microseconds, and null
 * is NA.
 *
 * @internal
 */
final class TimeFormat
{
    private const int MICRO = 1_000_000;

    /**
     * hms's as.character(): "-"?H:MM:SS with the fraction digits the column
     * needs. With $pad, hours are right-justified to a common width, as hms
     * does. NA stays null.
     *
     * @param list<?int> $ticks
     * @return list<?string>
     */
    public static function hms(array $ticks, bool $pad = true): array
    {
        $digits = self::digits($ticks);
        $parts = array_map(self::decompose(...), $ticks);
        $width = $pad ? max(2, ...array_map(fn (?array $p) => $p === null ? 2 : strlen($p['hours']), [...$parts, null])) : 0;

        return array_map(fn (?array $p) => $p === null ? null
            : ($p['sign'] ? '-' : '').str_pad($p['hours'], $width, ' ', STR_PAD_LEFT).':'.$p['minutes'].':'.$p['seconds'].self::tics($p['tics'], $digits),
            $parts);
    }

    /**
     * The cells hms's pillar shaft prints, and the NA indent: H:MM, with
     * :SS when any value has seconds or a fraction, or MM'SS" when no value
     * has hours. A column with a negative value gives the others a space for
     * the sign.
     *
     * @param list<?int> $ticks
     * @return array{0: list<?string>, 1: int}
     */
    public static function pillar(array $ticks): array
    {
        $digits = self::digits($ticks);
        $parts = array_map(self::decompose(...), $ticks);
        $present = array_filter($parts);
        $sign = array_any($present, fn (array $p) => $p['sign']);
        $hours = array_any($present, fn (array $p) => $p['hours'] !== '00');
        $seconds = $digits > 0 || array_any($present, fn (array $p) => $p['seconds'] !== '00');
        $width = max(2, ...array_map(fn (array $p) => strlen($p['hours']), [...$present, ['hours' => '']]));
        $prefix = fn (array $p) => $sign ? ($p['sign'] ? '-' : ' ') : '';

        if ($hours) {
            $cells = array_map(fn (?array $p) => $p === null ? null
                : $prefix($p).str_pad($p['hours'], $width, ' ', STR_PAD_LEFT).':'.$p['minutes']
                    .($seconds ? ':'.$p['seconds'].self::tics($p['tics'], $digits) : ''),
                $parts);
            $indent = ($sign ? 1 : 0) + $width + 3 + ($seconds ? 3 : 0) - 2;
        } else {
            $cells = array_map(fn (?array $p) => $p === null ? null
                : $prefix($p).$p['minutes']."'".$p['seconds'].self::tics($p['tics'], $digits).'"',
                $parts);
            $indent = ($sign ? 1 : 0) + 5 - 2;
        }

        return [$cells, $indent];
    }

    /**
     * The fraction digits a column needs, at most 6, as hms's format_tics().
     *
     * @param list<?int> $ticks
     */
    public static function digits(array $ticks): int
    {
        $digits = 0;
        foreach ($ticks as $t) {
            if ($t === null || ($tics = abs($t) % self::MICRO) === 0) {
                continue;
            }
            $digits = max($digits, strlen(rtrim(sprintf('%06d', $tics), '0')));
        }

        return $digits;
    }

    /** @return array{sign: bool, hours: string, minutes: string, seconds: string, tics: int}|null */
    private static function decompose(?int $t): ?array
    {
        if ($t === null) {
            return null;
        }
        $a = abs($t);

        return [
            'sign' => $t < 0,
            'hours' => sprintf('%02d', intdiv($a, 3600 * self::MICRO)),
            'minutes' => sprintf('%02d', intdiv($a % (3600 * self::MICRO), 60 * self::MICRO)),
            'seconds' => sprintf('%02d', intdiv($a % (60 * self::MICRO), self::MICRO)),
            'tics' => $a % self::MICRO,
        ];
    }

    private static function tics(int $tics, int $digits): string
    {
        return $digits === 0 ? '' : '.'.substr(sprintf('%06d', $tics), 0, $digits);
    }
}
