<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wildcard domain for generated hostnames
    |--------------------------------------------------------------------------
    |
    | Point a wildcard DNS record (*.example.com) at the server and set this to
    | "example.com". Generated hostnames then look like "shop-a1b2c3.example.com"
    | and never embed the server address, unlike sslip.io / nip.io names, which
    | encode the IP inside the hostname itself.
    */
    'wildcard_domain' => env('DASHY_WILDCARD_DOMAIN'),
];
