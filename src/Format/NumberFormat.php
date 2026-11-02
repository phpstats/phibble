<?php

declare(strict_types=1);

namespace Phibble\Format;

/**
 * Formats a vector of numbers the way R's format(x, trim = TRUE) does.
 *
 * Follows R's documented format() behavior with the default options
 * digits = 7 and scipen = 0: every finite value is shown with up to seven
 * significant digits, one common notation (fixed or scientific) and one
 * common precision are used for the whole vector, and fixed notation wins
 * whenever it is no wider than scientific.
 *
 * @internal
 */
final class NumberFormat
{
    private const DIGITS = 7;

    /** Beyond this many decimals fixed notation cannot be the narrower one. */
    private const MAX_FIXED_DECIMALS = 50;

    /**
     * Format each value; null, NaN and infinities become NA, NaN, Inf, -Inf.
     *
     * @param list<int|float|null> $values
     * @return list<string>
     */
    public static function format(array $values): array
    {
        $finite = [];
        $fixedDecimals = 0;
        $maxSignificant = 1;

        foreach ($values as $i => $value) {
            if ($value === null) {
                continue;
            }
            $number = (float) $value;
            if (!is_finite($number)) {
                continue;
            }
            if ($number == 0.0) {
                $number = 0.0; // drop the sign of negative zero
            }
            [$significant, $exponent] = self::significance($number);
            $maxSignificant = max($maxSignificant, $significant);
            $fixedDecimals = max($fixedDecimals, $significant - 1 - $exponent);
            $finite[$i] = $number;
        }

        $mantissaDecimals = $maxSignificant - 1;
        $scientific = array_map(
            static fn (float $n): string => self::scientific($n, $mantissaDecimals),
            $finite,
        );

        $chosen = $scientific;
        if ($finite !== [] && $fixedDecimals <= self::MAX_FIXED_DECIMALS) {
            $fixed = array_map(
                static fn (float $n): string => sprintf('%.' . $fixedDecimals . 'F', $n),
                $finite,
            );
            if (self::width($fixed) <= self::width($scientific)) {
                $chosen = $fixed;
            }
        }

        $result = [];
        foreach ($values as $i => $value) {
            $result[] = match (true) {
                $value === null => 'NA',
                isset($chosen[$i]) => $chosen[$i],
                is_nan((float) $value) => 'NaN',
                $value > 0 => 'Inf',
                default => '-Inf',
            };
        }

        return $result;
    }

    /**
     * Significant digits and decimal exponent of a value rounded to DIGITS.
     *
     * @return array{int, int}
     */
    private static function significance(float $number): array
    {
        if ($number == 0.0) {
            return [1, 0];
        }
        $text = sprintf('%.' . (self::DIGITS - 1) . 'e', abs($number));
        [$mantissa, $exponent] = explode('e', $text);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        return [max(1, strlen($digits)), (int) $exponent];
    }

    private static function scientific(float $number, int $decimals): string
    {
        $text = sprintf('%.' . $decimals . 'e', $number);
        [$mantissa, $exponent] = explode('e', $text);
        $power = (int) $exponent;
        $sign = $power < 0 ? '-' : '+';

        return $mantissa . 'e' . $sign . str_pad((string) abs($power), 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string> $strings */
    private static function width(array $strings): int
    {
        return max(array_map('strlen', $strings));
    }
}
