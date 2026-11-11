<?php

namespace MattBloomfield\RecipeHelper\controllers;

use MattBloomfield\RecipeHelper\sharecards\ShareCardService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /share-card/{key}: the Open Graph / X card image for a page.
 * Rendered on first request and cached in storage/app/share-cards.
 */
final class ShareCardController
{
    public function __invoke(string $key, ShareCardService $cards): BinaryFileResponse
    {
        $path = $cards->path($key) ?? abort(404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            // URLs carry a content hash (?v=…), so a week is safe; crawlers re-fetch on change.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
