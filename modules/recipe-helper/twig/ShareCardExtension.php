<?php

namespace MattBloomfield\RecipeHelper\twig;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use MattBloomfield\RecipeHelper\sharecards\ShareCardService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `shareCardUrl(element, page)` / `shareCardAlt(element, page)` for _partials/seo-meta.twig. */
class ShareCardExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('shareCardUrl', fn(?ElementInterface $element = null, string $page = 'default') => app(ShareCardService::class)->url($element, $page)),
            new TwigFunction('shareCardAlt', fn(?ElementInterface $element = null, string $page = 'default') => app(ShareCardService::class)->alt($element, $page)),
        ];
    }
}
