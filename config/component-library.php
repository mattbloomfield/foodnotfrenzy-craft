<?php

return [
    'browser' => [
        'enabled' => true,
        'requiresLogin' => false,
        'path' => 'component-library',
        'welcome' => '@docs/index',
        'preview' => '@preview',
    ],
    'root' => dirname(__DIR__) . '/templates/_components',
    'docs' => dirname(__DIR__) . '/library/docs',
    'aliases' => [
//        '@elements' => '02-elements',
//        '@modules' => '03-modules',
    ]
];