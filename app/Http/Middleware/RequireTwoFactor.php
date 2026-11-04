<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the emailed code once per session when the account enabled two-factor.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user === null || ! $user->two_factor_enabled) {
            return $next($request);
        }

        if (session('dashy_2fa') === $user->id) {
            return $next($request);
        }

        if ($request->is('two-factor*') || $request->is('logout') || $request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('security.challenge');
    }
}
