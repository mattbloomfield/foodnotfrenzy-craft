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
            new TwigFilter('decodeEntities', [$this, 'decodeEntities']),
        ];
    }

    public function niceFractions(mixed $value, float $scale, ?string $unit = null): array|string
    {
        return $this->formatter->format($value, $scale, $unit);
    }

    /**
     * Plain text for structured data: some imported recipe text is stored with
     * HTML entities (e.g. 9x13&#39;&#39;), which must not reach JSON-LD encoded.
     */
    public function decodeEntities(mixed $value): string
    {
        return html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function formatMinutes($minutes): string
    {
        return $this->formatter->formatMinutes((int)$minutes);
    }
}
