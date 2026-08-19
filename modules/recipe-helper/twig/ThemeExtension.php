<?php
namespace MattBloomfield\RecipeHelper\twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Reads theme cookies set by front-end JS. Craft's request API only
 * returns signed cookies, so plain JS cookies need a raw $_COOKIE read.
 */
class ThemeExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'Theme Extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rawCookie', [$this, 'rawCookie']),
        ];
    }

    public function rawCookie(string $name): ?string
    {
        $value = $_COOKIE[$name] ?? null;
        if ($value === null) {
            return null;
        }

        // Theme cookies are simple slugs; reject anything else
        return preg_match('/^[a-z-]{1,32}$/', $value) ? $value : null;
    }
}
