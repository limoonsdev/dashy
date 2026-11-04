<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => (string) config('security.headers.permissions_policy'),
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.(int) config('security.headers.hsts_max_age', 31536000).'; includeSubDomains';
        }

        $csp = trim((string) config('security.csp'));

        if ($csp !== '') {
            $header = config('security.csp_report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $headers[$header] = $csp;
        }

        foreach ($headers as $name => $value) {
            // Never overwrite a header a controller set on purpose.
            if ($value !== '' && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
