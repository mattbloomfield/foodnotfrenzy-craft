<?php

namespace MattBloomfield\RecipeHelper;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Cp\Events\CpNavItemsResolving;
use CraftCms\Cms\Element\Events\ElementEditorPayloadResolving;
use CraftCms\Cms\Element\Events\ElementLifecycleSaving;
use CraftCms\Cms\Element\Events\ElementSidebarHtmlResolving;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Route\TemplateRoute;
use CraftCms\Cms\Twig\Twig;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MattBloomfield\RecipeHelper\console\commands\CategorizeCommand;
use MattBloomfield\RecipeHelper\console\commands\NutritionCommand;
use MattBloomfield\RecipeHelper\console\commands\TaxonomyCommand;
use MattBloomfield\RecipeHelper\controllers\ApiController;
use MattBloomfield\RecipeHelper\controllers\CategoryRedirectController;
use MattBloomfield\RecipeHelper\controllers\ImportController;
use MattBloomfield\RecipeHelper\controllers\ShareCardController;
use MattBloomfield\RecipeHelper\http\HandleLegacyPathParam;
use MattBloomfield\RecipeHelper\listeners\NormalizeRecipeOnSave;
use MattBloomfield\RecipeHelper\listeners\NutritionSidebar;
use MattBloomfield\RecipeHelper\services\RecipeApiService;
use MattBloomfield\RecipeHelper\sharecards\ShareCardService;
use MattBloomfield\RecipeHelper\twig\RecipeFractionConverter;
use MattBloomfield\RecipeHelper\twig\RoutingExtension;
use MattBloomfield\RecipeHelper\twig\ShareCardExtension;
use MattBloomfield\RecipeHelper\twig\ThemeExtension;

/**
 * Recipe Helper: the site's custom recipe API, recipe import, nutrition
 * calculation and taxonomy tools, registered the Craft 6 / Laravel way.
 *
 * Registered in bootstrap/providers.php.
 */
class RecipeHelperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShareCardService::class);
    }

    public function boot(TemplateRoots $templateRoots, Twig $twig, HttpKernel $kernel): void
    {
        // `index.php?p=<path>` URLs, which Craft 5 supported.
        $kernel->prependMiddleware(HandleLegacyPathParam::class);

        // CP templates: `recipe-helper/import/index`, `recipe-helper/nutrition/_sidebar`.
        $templateRoots->register(TemplateMode::Cp, 'recipe-helper', __DIR__ . '/templates');

        // Component namespaces used by the site templates: `@block/recipe-meta`,
        // `@card/recipe`, `@global/nav`, `@listing/grid`, etc. (formerly
        // registered by madebyraygun/craft-component-library). Folder
        // components resolve to their index.twig.
        foreach (['block', 'card', 'global', 'listing'] as $namespace) {
            $templateRoots->register(TemplateMode::Site, '@' . $namespace, resource_path("views/_components/$namespace"));
        }

        $twig->registerExtension(new RecipeFractionConverter());
        $twig->registerExtension(new ThemeExtension());
        $twig->registerExtension(new RoutingExtension(), TemplateMode::Site);
        $twig->registerExtension(new ShareCardExtension(), TemplateMode::Site);

        // `craft.recipes` in Twig (templates/recipes/search.twig).
        CraftVariable::macro('recipes', fn() => new RecipeApiService());

        Event::listen(function(CpNavItemsResolving $event) {
            $event->navItems[] = new NavItem([
                'url' => 'recipe-import',
                'label' => 'Recipe Import',
                'icon' => 'arrow-down-to-bracket',
            ]);
        });

        // Normalize ingredient quantities and auto-calculate nutrition on save.
        Event::listen(ElementLifecycleSaving::class, NormalizeRecipeOnSave::class);

        // Nutrition summary + "force recalculate" on recipe edit screens.
        Event::listen(ElementEditorPayloadResolving::class, [NutritionSidebar::class, 'addToEditor']);
        Event::listen(ElementSidebarHtmlResolving::class, [NutritionSidebar::class, 'addToSidebarHtml']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CategorizeCommand::class,
                NutritionCommand::class,
                TaxonomyCommand::class,
            ]);
        }

        if (!$this->app->routesAreCached()) {
            $this->registerRoutes($this->app->make(Router::class), $this->app->make(Routes::class));
        }
    }

    private function registerRoutes(Router $router, Routes $routes): void
    {
        // Public site routes. Same middleware as Craft's own site routes; the
        // lazy session keeps these responses cookie-free.
        $router->middleware(['craft', 'craft.web'])->withLazySession()->group(function(Router $router) {
            // Read-only JSON API (see controllers/ApiController.php).
            $router->get('api/recipes', [ApiController::class, 'recipes']);
            $router->get('api/recipes/{slug}', [ApiController::class, 'recipe'])->where('slug', '[^\/]+');
            $router->get('api/categories', [ApiController::class, 'categories']);
            $router->get('api/openapi.json', [ApiController::class, 'openapi']);
            // CORS preflight on any api/* path.
            $router->options('api/{rest}', [ApiController::class, 'preflight'])->where('rest', '.*');

            // Fallback 301s for old category URLs after the taxonomy was
            // re-faceted. Real category URIs still render their element.
            $router->match(['GET', 'HEAD'], 'categories/{catUri}', CategoryRedirectController::class)
                ->where('catUri', '.+');

            // Social share card images (og:image / twitter:image). No .jpg
            // extension: nginx serves *.jpg paths as static files.
            $router->get('share-card/{key}', ShareCardController::class)
                ->where('key', 'entry-\d+|page-[a-z]+');
        });

        // Control panel page.
        $router->middleware(['web', 'craft', 'craft.cp', 'auth', 'can:accessCp'])
            ->prefix($routes->cpTriggerRoutePrefix())
            ->group(function(Router $router) {
                $router->get('recipe-import', fn(Request $request) => new TemplateRoute('recipe-helper/import/index', publicOnly: false)->handle($request));
            });

        // Control panel actions (`actionInput('recipe-helper/import/…')`).
        $router->middleware(['web', 'craft', 'craft.cp', 'auth', 'can:accessCp'])
            ->prefix($routes->joinRoutePrefix([$routes->cpActionTriggerRoutePrefix(), 'recipe-helper']))
            ->group(function(Router $router) {
                $router->post('import/import-url', [ImportController::class, 'importUrl']);
                $router->post('import/import-image', [ImportController::class, 'importImage']);
            });
    }
}
