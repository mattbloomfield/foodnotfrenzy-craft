<?php

namespace MattBloomfield\RecipeHelper\quantity;

/**
 * Converts Unicode fraction glyphs into quantities the recipe tools can parse.
 *
 * Fractions with exact decimal representations are stored as decimals.
 * Non-terminating values remain ASCII fractions so normalization never changes
 * their value.
 */
final class QuantityNormalizer
{
    /** @var array<string, array{0: int, 1: int}> */
    private const UNICODE_FRACTIONS = [
        '½' => [1, 2],
        '⅐' => [1, 7],
        '⅑' => [1, 9],
        '⅒' => [1, 10],
        '⅓' => [1, 3],
        '⅔' => [2, 3],
        '¼' => [1, 4],
        '¾' => [3, 4],
        '⅕' => [1, 5],
        '⅖' => [2, 5],
        '⅗' => [3, 5],
        '⅘' => [4, 5],
        '⅙' => [1, 6],
        '⅚' => [5, 6],
        '⅛' => [1, 8],
        '⅜' => [3, 8],
        '⅝' => [5, 8],
        '⅞' => [7, 8],
    ];

    public static function normalize(mixed $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '' || !preg_match('/[½¼¾⅐-⅞]/u', $value)) {
            return $value;
        }

        $glyphs = preg_quote(implode('', array_keys(self::UNICODE_FRACTIONS)), '/');

        return preg_replace_callback(
            '/(?:(\d+)[\s\x{00a0}]*)?([' . $glyphs . '])/u',
            static function(array $matches): string {
                [$numerator, $denominator] = self::UNICODE_FRACTIONS[$matches[2]];
                $whole = isset($matches[1]) && $matches[1] !== '' ? (int)$matches[1] : 0;

                return self::format($whole * $denominator + $numerator, $denominator);
            },
            $value
        ) ?? $value;
    }

    private static function format(int $numerator, int $denominator): string
    {
        $gcd = self::gcd($numerator, $denominator) ?: 1;
        $numerator = intdiv($numerator, $gcd);
        $denominator = intdiv($denominator, $gcd);

        // A reduced fraction terminates in base 10 only when its denominator's
        // prime factors are all 2 or 5.
        $remainder = $denominator;
        while ($remainder % 2 === 0) {
            $remainder = intdiv($remainder, 2);
        }
        while ($remainder % 5 === 0) {
            $remainder = intdiv($remainder, 5);
        }

        if ($remainder === 1) {
            return rtrim(rtrim(number_format($numerator / $denominator, 6, '.', ''), '0'), '.');
        }

        $whole = intdiv($numerator, $denominator);
        $fractionNumerator = $numerator % $denominator;

        return $whole > 0
            ? $whole . ' ' . $fractionNumerator . '/' . $denominator
            : $fractionNumerator . '/' . $denominator;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
