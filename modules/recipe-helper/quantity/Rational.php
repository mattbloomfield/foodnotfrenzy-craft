<?php
namespace MattBloomfield\RecipeHelper\quantity;

/**
 * Exact fraction arithmetic for recipe quantities, so scaling never drifts
 * through float round-trips (1/3 × 3 = 1 exactly, not 0.999…).
 */
final class Rational
{
    private const FRACTION_GLYPHS = [
        '1/2' => '½',
        '1/3' => '⅓', '2/3' => '⅔',
        '1/4' => '¼', '3/4' => '¾',
        '1/5' => '⅕', '2/5' => '⅖', '3/5' => '⅗', '4/5' => '⅘',
        '1/6' => '⅙', '5/6' => '⅚',
        '1/8' => '⅛', '3/8' => '⅜', '5/8' => '⅝', '7/8' => '⅞',
    ];

    // Denominators whose fractions all have Unicode glyphs once reduced
    private const DISPLAY_DENOMINATORS = [1, 2, 3, 4, 5, 6, 8];

    public readonly int $num;
    public readonly int $den;

    public function __construct(int $num, int $den)
    {
        if ($den === 0) {
            throw new \InvalidArgumentException('Denominator cannot be zero');
        }
        if ($den < 0) {
            $num = -$num;
            $den = -$den;
        }
        $gcd = self::gcd(abs($num), $den) ?: 1;
        $this->num = intdiv($num, $gcd);
        $this->den = intdiv($den, $gcd);
    }

    /**
     * Parse "2", "1.5", "1/2", or "1 1/2". Returns null if the string
     * isn't a recognizable quantity.
     */
    public static function fromString(string $value): ?self
    {
        $value = trim($value);

        // Decimal or whole number — use the decimal digits directly so "1.5" is exactly 3/2
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $m)) {
            $decimals = $m[2] ?? '';
            if ($decimals === '') {
                return new self((int)$m[1], 1);
            }
            $den = 10 ** strlen($decimals);
            return new self((int)$m[1] * $den + (int)$decimals, $den);
        }

        // Mixed number: "1 1/2"
        if (preg_match('~^(\d+)\s+(\d+)\s*/\s*(\d+)$~', $value, $m) && (int)$m[3] !== 0) {
            return new self((int)$m[1] * (int)$m[3] + (int)$m[2], (int)$m[3]);
        }

        // Simple fraction: "1/2"
        if (preg_match('~^(\d+)\s*/\s*(\d+)$~', $value, $m) && (int)$m[2] !== 0) {
            return new self((int)$m[1], (int)$m[2]);
        }

        return null;
    }

    /**
     * Best rational approximation of a float (denominator ≤ 100).
     */
    public static function fromFloat(float $value): self
    {
        if (floor($value) === $value) {
            return new self((int)$value, 1);
        }

        $best = [(int)round($value), 1];
        $bestError = abs($value - round($value));

        for ($den = 2; $den <= 100; $den++) {
            $num = (int)round($value * $den);
            $error = abs($value - $num / $den);
            if ($error < $bestError) {
                $best = [$num, $den];
                $bestError = $error;
                if ($error < 1e-9) {
                    break;
                }
            }
        }

        return new self($best[0], $best[1]);
    }

    public function times(self $other): self
    {
        return new self($this->num * $other->num, $this->den * $other->den);
    }

    public function timesInt(int $factor): self
    {
        return new self($this->num * $factor, $this->den);
    }

    public function overInt(int $divisor): self
    {
        return new self($this->num, $this->den * $divisor);
    }

    public function toFloat(): float
    {
        return $this->num / $this->den;
    }

    public function isWhole(): bool
    {
        return $this->den === 1;
    }

    /**
     * Whether this renders as a whole number or a glyph-friendly fraction.
     * Used to decide if a unit conversion produces a presentable amount.
     */
    public function isDisplayable(): bool
    {
        return in_array($this->den, self::DISPLAY_DENOMINATORS, true);
    }

    /**
     * "2", "½", "1 ⅔" — falls back to "n/d" text when no glyph exists.
     */
    public function toDisplayString(): string
    {
        if ($this->den === 1) {
            return (string)$this->num;
        }

        $whole = intdiv($this->num, $this->den);
        $remainder = $this->num % $this->den;
        $fraction = self::FRACTION_GLYPHS["$remainder/$this->den"] ?? "$remainder/$this->den";

        return $whole > 0 ? "$whole $fraction" : $fraction;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return $a;
    }
}
