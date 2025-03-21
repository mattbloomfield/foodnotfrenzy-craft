<?php

return [
    'sitemapEnabled' => true,
    'sitemapLimit' => 500,
    'sitemapConfig' => [
        'elements' => [
            'recipes' => ['changefreq' => 'weekly', 'priority' => 1],
            'categories' => ['changefreq' => 'monthly', 'priority' => 0.5],
        ],
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