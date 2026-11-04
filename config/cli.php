<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bun executable
    |--------------------------------------------------------------------------
    |
    | The download page compiles the CLI into standalone binaries. Bun does the
    | cross-compilation; leave this empty to look for "bun" on PATH.
    |
    */
    'bun_path' => env('CLI_BUN_PATH', ''),
];
