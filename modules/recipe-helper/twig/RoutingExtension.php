<?php
namespace MattBloomfield\RecipeHelper\twig;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Route\MatchedElement;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `matchedElement()`: the element the current request’s URI resolved to, or
 * `false` (Craft 5’s `craft.app.urlManager.getMatchedElement()`).
 */
class RoutingExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('matchedElement', fn(): ElementInterface|false => MatchedElement::get()),
        ];
    }
}
