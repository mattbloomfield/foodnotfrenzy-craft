<?php

namespace MattBloomfield\RecipeHelper\support;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Arr;
use GuzzleHttp\Client;

use function GuzzleHttp\default_user_agent;

/**
 * Guzzle clients for the outbound API calls, configured the way Craft 5’s
 * `Craft::createGuzzleClient()` configured them: a Craft User-Agent by
 * default, `config/craft/guzzle.php` if there is one, and the `httpProxy`
 * general config setting.
 */
class HttpClient
{
    public static function create(array $config = []): Client
    {
        $defaultConfig = [
            'headers' => [
                'User-Agent' => 'Craft/' . Cms::version() . ' ' . default_user_agent(),
            ],
        ];

        $config = Arr::merge($defaultConfig, config('craft.guzzle', []), $config);

        if ($proxy = Cms::config()->httpProxy) {
            $config['proxy'] = $proxy;
        }

        return new Client($config);
    }
}
