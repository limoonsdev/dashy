<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the locale chosen in the picker. The session value is only honoured
 * when the catalog lists it, so a stale or hand-edited value falls back safely.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('locales.supported', []));
        $locale = $request->session()->get('locale');

        if (is_string($locale) && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
