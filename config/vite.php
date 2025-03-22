<?php

use craft\helpers\App;

try {
    $config = [
        'checkDevServer' => true,
        'devServerInternal' => 'http://localhost:5173',
        'devServerPublic' => App::env('PRIMARY_SITE_URL') . ':5174',
        'useDevServer' => App::parseBooleanEnv('$VITE_USE_DEV_SERVER'),
        'errorEntry' => '',
        'serverPublic' => App::env('PRIMARY_SITE_URL') . '/dist/',
        'manifestPath' => '@webroot/dist/.vite/manifest.json',
    ];
    return $config;
} catch (\yii\base\Exception $e) {
    return [];
}