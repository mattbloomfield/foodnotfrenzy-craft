<?php

if (getenv('CRAFT_ENVIRONMENT') === 'production') {
    return [
        'showLabel' => false,
    ];
}

return [
    'showLabel' => true,
    'labelText' => "Dev",
    'labelColor' => '#115643',
    'textColor' => '#ffffff',
    'targetSelector' => '#global-header:before',
];