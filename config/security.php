<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security headers
    |--------------------------------------------------------------------------
    |
    | Applied to every web response by App\Http\Middleware\SecurityHeaders.
    | HSTS is only sent when the request already arrived over HTTPS, so local
    | HTTP development is not affected.
    */
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Report-only by default. The dashboard uses inline Alpine and Livewire
    | bootstrap scripts, so an enforced policy must be tuned against a real
    | instance first: set SECURITY_CSP_REPORT_ONLY=false when the report shows
    | no violations. Keep frame-ancestors and object-src locked down.
    */
    'csp' => env(
        'SECURITY_CSP',
        "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; "
        ."img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."connect-src 'self' ws: wss: https:; font-src 'self' data:; form-action 'self'",
    ),

    'csp_report_only' => env('SECURITY_CSP_REPORT_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Malware scanning (ClamAV)
    |--------------------------------------------------------------------------
    |
    | Uploads are streamed to a clamd daemon over TCP with the INSTREAM
    | protocol, so a file is never written to disk before it is scanned.
    | CLAMAV_HOST empty disables scanning. CLAMAV_REQUIRED=true fails closed:
    | when clamd is unreachable, uploads are rejected instead of accepted.
    */
    'clamav' => [
        'host' => env('CLAMAV_HOST'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 10),
        'required' => (bool) env('CLAMAV_REQUIRED', false),
        'max_bytes' => (int) env('CLAMAV_MAX_BYTES', 268435456),
    ],
];
