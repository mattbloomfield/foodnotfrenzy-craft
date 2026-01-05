<?php
namespace MattBloomfield\RecipeHelper\twig;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RecipeFractionConverter extends AbstractExtension
{
    // Unit conversion constants
    private const UNIT_CONVERSIONS = [
        // Volume conversions
        'teaspoon' => [
            'tablespoon' => 1/3,
            'cup' => 1/48
        ],
        'tablespoon' => [
            'teaspoon' => 3,
            'cup' => 1/16
        ],
        'cup' => [
            'tablespoon' => 16,
            'teaspoon' => 48
        ],
        // Weight conversions
        'ounce' => [
            'pound' => 1/16
        ],
        'pound' => [
            'ounce' => 16
        ]
    ];

    // Unit aliases for normalization
    private const UNIT_ALIASES = [
        'tsp' => 'teaspoon',
        'teaspoons' => 'teaspoon',
        't' => 'teaspoon',
        'tbsp' => 'tablespoon',
        'tablespoons' => 'tablespoon',
        'T' => 'tablespoon',
        'c' => 'cup',
        'cups' => 'cup',
        'oz' => 'ounce',
        'ounces' => 'ounce',
        'lb' => 'pound',
        'lbs' => 'pound',
        'pounds' => 'pound'
    ];

    // Display formats for units
    private const UNIT_DISPLAY = [
        'teaspoon' => 'teaspoon',
        'tablespoon' => 'tablespoon',
        'cup' => 'cup',
        'ounce' => 'ounce',
        'pound' => 'pound'
    ];

    // Threshold values for unit conversions
    private const CONVERSION_THRESHOLDS = [
        'teaspoon_to_tablespoon' => 3,     // 3+ tsp → convert to tbsp
        'tablespoon_to_cup' => 4,          // 4+ tbsp → convert to cup
        'ounce_to_pound' => 16             // 16+ oz → convert to pound
    ];

    // Common fractions we want to ensure are properly recognized
    private const COMMON_FRACTIONS = [
        '1/4' => 0.25,
        '1/3' => 0.333333333333,
        '1/2' => 0.5,
        '2/3' => 0.666666666667,
        '3/4' => 0.75,
        '1/8' => 0.125,
        '3/8' => 0.375,
        '5/8' => 0.625,
        '7/8' => 0.875
    ];

    public function getName(): string
    {
        return 'Recipe Fraction Converter';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('niceFractions', [$this, 'niceFractions']),
            new TwigFilter('formatMinutes', [$this, 'formatMinutes']),
        ];
    }

    /**
     * Convert a quantity and unit to the most logical format
     *
     * @param mixed $value The original quantity value
     * @param float $scale The scaling factor
     * @param string|null $unit The original unit (optional)
     * @return array|string|null Formatted value and unit
     */
    public function niceFractions($value, float $scale, ?string $unit = null): array|string|null
    {
        if ($value === 0 || $value === null || $value === '') {
            return '';
        }

        // Scale the value
        $scaledValue = $this->_getScaledValue($value, $scale);

        // If no unit was provided, just format the value with nice fractions
        if ($unit === null || $unit === 'none' || $unit === '') {
            return $this->formatFractionDisplay($scaledValue);
        }

        // Normalize unit name
        $normalizedUnit = $this->normalizeUnit($unit);

        // Convert numeric string to actual number for calculations
        $numericValue = $this->stringToNumber($scaledValue);

        // Optimize the unit and value
        list($optimizedValue, $optimizedUnit) = $this->optimizeUnitAndValue($numericValue, $normalizedUnit);

        // Format the optimized value for display if needed
        // (optimizedValue is already in fraction format after optimizeUnitAndValue changes)
        $formattedValue = is_string($optimizedValue)
            ? $this->formatFractionDisplay($optimizedValue)
            : $this->formatFractionDisplay($this->convertToFractionString($optimizedValue));

        // Get the display format for the unit
        $displayUnit = self::UNIT_DISPLAY[$optimizedUnit] ?? $optimizedUnit;

        // Add plural 's' if value is not 1
        if ($numericValue != 1 && $displayUnit === 'cup') {
            $displayUnit = 'cups';
        } else if ($numericValue != 1 && substr($displayUnit, -1) !== 's') {
            $displayUnit = $displayUnit . 's';
        }

        return [
            'value' => $formattedValue,
            'unit' => $displayUnit
        ];
    }

    /**
     * Normalize unit names to a standard format
     */
    private function normalizeUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));

        return self::UNIT_ALIASES[$unit] ?? $unit;
    }

    /**
     * Convert a string representation of a number to a numeric value
     */
    private function stringToNumber(string $value): float
    {
        // Handle whole numbers
        if (is_numeric($value)) {
            return (float)$value;
        }

        // Handle mixed numbers (e.g., "1 1/2")
        if (strpos($value, ' ') !== false) {
            $parts = explode(' ', $value);
            $wholeNumber = (float)$parts[0];

            if (isset($parts[1]) && strpos($parts[1], '/') !== false) {
                list($numerator, $denominator) = explode('/', $parts[1]);
                return $wholeNumber + ((float)$numerator / (float)$denominator);
            }

            return $wholeNumber;
        }

        // Handle simple fractions (e.g., "1/2")
        if (strpos($value, '/') !== false) {
            list($numerator, $denominator) = explode('/', $value);
            return (float)$numerator / (float)$denominator;
        }

        return (float)$value;
    }

    /**
     * Optimize the unit and value combination
     *
     * @return array [optimizedValue, optimizedUnit]
     */
    private function optimizeUnitAndValue(float $value, string $unit): array
    {
        // Check if we should convert to a larger unit
        if ($unit === 'teaspoon' && $value >= self::CONVERSION_THRESHOLDS['teaspoon_to_tablespoon']) {
            $newValue = $value / 3;
            // Only convert if it makes a clean fraction
            if ($this->isCleanFraction($newValue, 0.01)) {
                return [$this->convertToFractionString($newValue), 'tablespoon'];
            }
        } elseif ($unit === 'tablespoon' && $value >= self::CONVERSION_THRESHOLDS['tablespoon_to_cup']) {
            $newValue = $value / 16;
            // Only convert if it makes a clean fraction
            if ($this->isCleanFraction($newValue, 0.01)) {
                return [$this->convertToFractionString($newValue), 'cup'];
            }
        } elseif ($unit === 'ounce' && $value >= self::CONVERSION_THRESHOLDS['ounce_to_pound']) {
            $newValue = $value / 16;
            return [$this->convertToFractionString($newValue), 'pound'];
        }

        // Check if we should convert to a smaller unit
        if ($unit === 'tablespoon' && $value < 1) {
            return [$this->convertToFractionString($value * 3), 'teaspoon'];
        } elseif ($unit === 'cup' && $value < 0.25) {
            return [$this->convertToFractionString($value * 16), 'tablespoon'];
        } elseif ($unit === 'pound' && $value < 1) {
            return [$this->convertToFractionString($value * 16), 'ounce'];
        }

        // No conversion needed
        return [$this->convertToFractionString($value), $unit];
    }

    /**
     * Check if a value represents a "clean" fraction (1/4, 1/3, 1/2, 2/3, 3/4, etc.)
     */
    private function isCleanFraction(float $value, float $tolerance = 0.001): bool
    {
        $wholeNumber = floor($value);
        $fraction = $value - $wholeNumber;

        // Whole number
        if ($fraction < $tolerance) {
            return true;
        }

        // Check against our common fractions
        foreach (self::COMMON_FRACTIONS as $fractionStr => $fractionValue) {
            if (abs($fraction - $fractionValue) < $tolerance) {
                return true;
            }
        }

        // Common denominators for "clean" fractions
        $denominators = [2, 3, 4, 8];

        foreach ($denominators as $denominator) {
            for ($numerator = 1; $numerator < $denominator; $numerator++) {
                $cleanFraction = $numerator / $denominator;
                if (abs($fraction - $cleanFraction) < $tolerance) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Format a numeric value as a nice-looking fraction string
     */
    private function formatFractionDisplay(string $value): string
    {
        // Define regex pattern to match mixed numbers and fractions
        $pattern = '/(\d+)\s+(\d+)\/(\d+)|(\d+)\/(\d+)/';

        // Map of fractions to Unicode characters
        $fractionMap = [
            '1/4' => '¼',
            '1/2' => '½',
            '3/4' => '¾',
            '1/3' => '⅓',
            '2/3' => '⅔',
            '1/5' => '⅕',
            '2/5' => '⅖',
            '3/5' => '⅗',
            '4/5' => '⅘',
            '1/6' => '⅙',
            '5/6' => '⅚',
            '1/8' => '⅛',
            '3/8' => '⅜',
            '5/8' => '⅝',
            '7/8' => '⅞'
        ];

        // Process the input
        return preg_replace_callback($pattern, static function($matches) use ($fractionMap) {
            // Check if it's a mixed number (1 1/2 format)
            if (!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3])) {
                $whole = $matches[1];
                $numerator = $matches[2];
                $denominator = $matches[3];

                // Try to use Unicode fraction if available
                $fractionString = "$numerator/$denominator";
                if (isset($fractionMap[$fractionString])) {
                    return $whole . ' ' . $fractionMap[$fractionString];
                }

                // If no Unicode fraction available, return the original format
                return $whole . ' ' . $fractionString;
            }

            // It's a simple fraction (1/2 format)
            if (!empty($matches[4]) && !empty($matches[5])) {
                $numerator = $matches[4];
                $denominator = $matches[5];

                $fractionString = "$numerator/$denominator";
                if (isset($fractionMap[$fractionString])) {
                    return $fractionMap[$fractionString];
                }

                // If no Unicode fraction available, return the original format
                return $fractionString;
            }

            // Fallback to original match
            return $matches[0];
        }, $value);
    }

    private function _getScaledValue($value, $scale): string
    {
        // sometimes value may be a range. Just return as is in that case
        if (is_string($value) && str_contains($value, '-')) {
            return $value;
        }

        // Handle case where $value is already a numeric value
        if (is_numeric($value)) {
            $result = $value * $scale;
            return $this->convertToFractionString($result);
        }

        $valueParts = explode(' ', $value);
        $whole = 0;
        $fraction = 0;

        // Handle the first part (either whole number or fraction)
        $fractionParts = explode('/', $valueParts[0]);
        if (count($fractionParts) > 1) {
            // It's a fraction like "1/2"
            $numerator = (int)$fractionParts[0];
            $denominator = (int)$fractionParts[1];
            if ($denominator !== 0) {
                $fraction = $numerator / $denominator;
            }
        } else {
            // It's a whole number
            $whole = (float)$valueParts[0];
        }

        // Handle mixed numbers like "1 1/2"
        if (count($valueParts) > 1) {
            $fractionParts = explode('/', $valueParts[1]);
            if (count($fractionParts) > 1) {
                $fraction = $fraction + $fractionParts[0] / $fractionParts[1];
            }
        }

        // Calculate the total value with scaling
        $result = ($whole + $fraction) * $scale;

        // Convert to a fraction string
        return $this->convertToFractionString($result);
    }

    /**
     * Convert a decimal number to a simplified fraction string
     */
    private function convertToFractionString($decimal): string
    {
        // Handle string input
        if (is_string($decimal) && strpos($decimal, '/') !== false) {
            // Already a fraction, return as is
            return $decimal;
        }

        $decimal = (float)$decimal;

        // Handle whole numbers
        if (floor($decimal) == $decimal) {
            return (string)$decimal;
        }

        // Extract whole part and decimal part
        $wholePart = floor($decimal);
        $decimalPart = $decimal - $wholePart;

        // Check for common fractions first
        foreach (self::COMMON_FRACTIONS as $fraction => $value) {
            if (abs($decimalPart - $value) < 0.0001) {
                if ($wholePart > 0) {
                    return "$wholePart $fraction";
                } else {
                    return $fraction;
                }
            }
        }

        // Convert decimal part to fraction
        list($numerator, $denominator) = $this->decimalToFraction($decimalPart);

        // Format as a mixed number or simple fraction
        if ($wholePart > 0) {
            return "$wholePart $numerator/$denominator";
        } else {
            return "$numerator/$denominator";
        }
    }

    /**
     * Convert a decimal to a simplified fraction
     *
     * @return array [numerator, denominator]
     */
    private function decimalToFraction($decimal): array
    {
        // Handle special case for zero
        if ($decimal == 0) {
            return [0, 1];
        }

        // First, try some common fractions that are problematic in floating point
        $commonFractions = [
            1/3 => [1, 3],
            2/3 => [2, 3],
            1/6 => [1, 6],
            5/6 => [5, 6]
        ];

        foreach ($commonFractions as $value => $fraction) {
            if (abs($decimal - $value) < 0.0001) {
                return $fraction;
            }
        }

        // Implementation of continued fraction algorithm
        $maximumDenominator = 100; // Limit denominator to avoid huge fractions

        $bestApproximation = [0, 1];
        $bestError = $decimal;

        for ($denominator = 1; $denominator <= $maximumDenominator; $denominator++) {
            $numerator = round($decimal * $denominator);
            $error = abs($decimal - $numerator / $denominator);

            if ($error < $bestError) {
                $bestError = $error;
                $bestApproximation = [$numerator, $denominator];

                // If we've found an exact (or extremely close) match, return it
                if ($error < 0.0000001) {
                    break;
                }
            }
        }

        // Simplify the fraction
        $gcd = $this->findGCD($bestApproximation[0], $bestApproximation[1]);
        return [
            (int)($bestApproximation[0] / $gcd),
            (int)($bestApproximation[1] / $gcd)
        ];
    }

    /**
     * Find the greatest common divisor using Euclidean algorithm
     */
    private function findGCD($a, $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b != 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return $a;
    }

    public function formatMinutes($minutes): string
    {
        // Validate input
        $minutes = (int)$minutes;

        // Handle special case: 0 minutes
        if ($minutes === 0) {
            return '0 min';
        }

        // Calculate hours and remaining minutes
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        // Build the output string
        $output = '';

        // Add hours if we have any
        if ($hours > 0) {
            $output = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        // Add minutes if we have any
        if ($remainingMinutes > 0) {
            if (!empty($output)) {
                $output .= ' ';
            }
            $output .= $remainingMinutes . ' min';
        }

        return $output;
    }
}