<?php
namespace MattBloomfield\RecipeHelper\twig;

use MattBloomfield\RecipeHelper\quantity\QuantityFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Thin Twig adapter — the real logic lives in quantity/QuantityFormatter.
 */
class RecipeFractionConverter extends AbstractExtension
{
    private QuantityFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new QuantityFormatter();
    }

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

    public function niceFractions(mixed $value, float $scale, ?string $unit = null): array|string
    {
        return $this->formatter->format($value, $scale, $unit);
    }

    public function formatMinutes($minutes): string
    {
        return $this->formatter->formatMinutes((int)$minutes);
    }
}
