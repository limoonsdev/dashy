<?php

namespace App\Http\Middleware;

use App\Services\Security\SuspiciousIpGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks known scanners before any controller runs. Local addresses are always
 * allowed so health checks and the console keep working.
 */
class BlockSuspiciousIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();
        $trusted = array_filter(array_map('trim', explode(',', (string) env('DASHY_TRUSTED_IPS', ''))));

        if (app()->runningInConsole() || app()->runningUnitTests()
            || in_array($ip, ['127.0.0.1', '::1'], true)
            || in_array($ip, $trusted, true)) {
            return $next($request);
        }

        $guard = new SuspiciousIpGuard;

        if ($guard->isBlocked($ip)) {
            abort(403, 'This address has been blocked.');
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($guard->isSuspicious($path, $request->userAgent(), $guard->countRecentNotFound($ip))) {
            $guard->strike($ip, 'Suspicious request: '.$path);

            abort(403, 'This request has been blocked.');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            $guard->recordNotFound($ip);
        }

        return $response;
    }
}
