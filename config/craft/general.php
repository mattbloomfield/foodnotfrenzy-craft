<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/Config/GeneralConfig.php.
 *
 * @see \CraftCms\Cms\Config\GeneralConfig
 */

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Support\Env;

$isProduction = Env::get('APP_ENV') === 'production';

return GeneralConfig::create()
    // Set the default week start day for date pickers (0 = Sunday, 1 = Monday, etc.)
    ->defaultWeekStartDay(1)
    // Preload Single entries as Twig variables
    ->preloadSingles()
    // Prevent user enumeration attacks
    ->preventUserEnumeration()
    // Set the @webroot alias so the clear-caches command knows where to find CP resources
    ->aliases([
        '@webroot' => public_path(),
    ])
    ->allowAdminChanges(!$isProduction)
    ->allowUpdates(!$isProduction)
    // Session length (1 year) now lives in config/session.php (SESSION_LIFETIME).

;
