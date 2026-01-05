<?php

return [
    'sitemapEnabled' => true,
    'sitemapLimit' => 500,
    'sitemapConfig' => [
        'elements' => [
            'recipes' => ['changefreq' => 'weekly', 'priority' => 1],
            'categories' => ['changefreq' => 'monthly', 'priority' => 0.5],
            'home' => ['changefreq' => 'daily', 'priority' => 1],
            'categoryListing' => ['changefreq' => 'daily', 'priority' => 1],
        ],
        'custom' => [
            '/latest' => ['changefreq' => 'weekly', 'priority' => 1],
        ]
    ],


    'defaultProfile' => 'standard',

    'fieldProfiles' => [
        'standard' => [
            'title' => ['title'],
            'description' => ['description'],
            'image' => ['image']
        ]
    ],
];