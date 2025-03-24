<?php
namespace MattBloomfield\RecipeHelper\twig;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RecipeFractionConverter extends AbstractExtension
{
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

    public function niceFractions($value): array|string|null
    {
        // Define mapping of common fractions to Unicode characters

        // Define regex pattern to match mixed numbers and fractions
        $pattern = '/(\d+)\s+(\d+)\/(\d+)|(\d+)\/(\d+)/';

        // Process the input
        return preg_replace_callback($pattern, static function($matches) {
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