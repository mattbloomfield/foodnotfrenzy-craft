<?php

namespace MattBloomfield\RecipeHelper\http;

use Closure;
use Illuminate\Http\Request;

/**
 * Keeps Craft 5’s `index.php?p=<path>` URLs working: the request is handled as
 * if `<path>` had been requested, as the Yii adapter did (its
 * NormalizeLegacyPath middleware). Craft 6 only routes by the URL path.
 */
class HandleLegacyPathParam
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->uri()->path() !== 'index.php' || !$request->has('p')) {
            return $next($request);
        }

        $query = $request->query->all();
        unset($query['p']);

        $normalizedRequest = $request->duplicateWithUri('/' . ltrim((string)$request->get('p'), '/'), $query);
        $normalizedRequest->request->remove('p');

        app()->instance('request', $normalizedRequest);

        return $next($normalizedRequest);
    }
}
