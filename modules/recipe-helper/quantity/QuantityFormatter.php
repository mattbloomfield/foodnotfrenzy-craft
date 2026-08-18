<?php
namespace MattBloomfield\RecipeHelper\quantity;

/**
 * Scales ingredient quantities and formats them for display:
 * parse (exact rationals) → scale → optimize unit → render glyph fractions.
 */
final class QuantityFormatter
{
    /**
     * Returns ['value' => ..., 'unit' => ...] when a unit is given,
     * otherwise just the formatted value string.
     */
    public function format(mixed $value, float $scale, ?string $unit = null): array|string
    {
        $raw = trim((string)($value ?? ''));
        $hasUnit = $unit !== null && $unit !== '' && $unit !== 'none';
        $unitKey = $hasUnit ? UnitRegistry::resolve($unit) : null;

        // No quantity — nothing to scale, but still name the unit ("pinch salt")
        if ($raw === '' || $raw === '0') {
            return $hasUnit
                ? ['value' => '', 'unit' => $unitKey ? UnitRegistry::displayName($unitKey, false) : $unit]
                : '';
        }

        [$min, $max] = $this->parseQuantity($raw);

        // Unparseable (e.g. "a few") — pass through untouched
        if ($min === null) {
            return $hasUnit
                ? ['value' => $raw, 'unit' => $unitKey ? UnitRegistry::displayName($unitKey, true) : $unit]
                : $raw;
        }

        $scaleFactor = Rational::fromFloat($scale);
        $min = $min->times($scaleFactor);
        $max = $max?->times($scaleFactor);

        if (!$hasUnit) {
            return $this->formatAmount($min, $max);
        }

        // Only optimize the unit for single values; ranges keep their unit
        if ($unitKey !== null && $max === null) {
            [$min, $unitKey] = UnitRegistry::optimize($min, $unitKey);
        }

        $plural = ($max ?? $min)->toFloat() > 1;

        return [
            'value' => $this->formatAmount($min, $max),
            'unit' => $unitKey ? UnitRegistry::displayName($unitKey, $plural) : $unit,
        ];
    }

    public function formatMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes . ' min';
        }

        return implode(' ', $parts);
    }

    /**
     * Parse a quantity that may be a single value or a range ("1-2").
     *
     * @return array{0: ?Rational, 1: ?Rational} [value, rangeEnd] — rangeEnd
     *         is null for single values; both null when unparseable
     */
    private function parseQuantity(string $raw): array
    {
        $single = Rational::fromString($raw);
        if ($single !== null) {
            return [$single, null];
        }

        $parts = preg_split('/\s*[-–—]\s*/u', $raw);
        if (count($parts) === 2) {
            $min = Rational::fromString($parts[0]);
            $max = Rational::fromString($parts[1]);
            if ($min !== null && $max !== null) {
                return [$min, $max];
            }
        }

        return [null, null];
    }

    private function formatAmount(Rational $min, ?Rational $max): string
    {
        return $max === null
            ? $min->toDisplayString()
            : $min->toDisplayString() . '-' . $max->toDisplayString();
    }
}
