<?php

use App\Services\Vms\VmCatalog;

return [
    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | "proxmox" uses the API below. "incus" uses the Incus/LXD API and is meant
    | for setups that cannot run Proxmox: images are pulled from the simplestreams
    | remote instead of ISO files.
    */
    'provider' => env('VM_PROVIDER', 'proxmox'),

    'incus' => [
        // No placeholder: an empty URL means "not configured" instead of a
        // failed request to the Dashy container itself.
        'url' => env('INCUS_URL'),
        'token' => env('INCUS_TOKEN'),
        'project' => env('INCUS_PROJECT', 'default'),
        'remote' => env('INCUS_REMOTE', 'images'),
        'image' => env('INCUS_IMAGE', 'debian/12'),
        'storage' => env('INCUS_STORAGE', 'default'),
        'network' => env('INCUS_NETWORK', 'incusbr0'),
        'verify_tls' => filter_var(env('INCUS_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('INCUS_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxmox VE connection
    |--------------------------------------------------------------------------
    |
    | Create an API token in Proxmox (Datacenter > Permissions > API Tokens)
    | with VM.Allocate, VM.Config.*, Datastore.AllocateSpace and Datastore.Audit
    | on the target node/storage. Token id format: user@realm!token-name.
    | Username/password is only used when no token is configured.
    */
    'proxmox' => [
        'url' => env('PROXMOX_URL'),
        'token_id' => env('PROXMOX_TOKEN_ID'),
        'token_secret' => env('PROXMOX_TOKEN_SECRET'),
        'username' => env('PROXMOX_USERNAME'),
        'password' => env('PROXMOX_PASSWORD'),
        'node' => env('PROXMOX_NODE', 'pve'),
        'storage' => env('PROXMOX_STORAGE', 'local-lvm'),
        'iso_storage' => env('PROXMOX_ISO_STORAGE', 'local'),
        'bridge' => env('PROXMOX_BRIDGE', 'vmbr0'),
        'verify_tls' => filter_var(env('PROXMOX_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('PROXMOX_TIMEOUT', 30),
        'task_timeout' => (int) env('PROXMOX_TASK_TIMEOUT', 900),
    ],

    'defaults' => [
        'cores' => (int) env('VM_DEFAULT_CORES', 2),
        'memory' => (int) env('VM_DEFAULT_MEMORY', 2048),
        'disk' => (int) env('VM_DEFAULT_DISK', 20),
        'ssh_keys' => env('VM_SSH_KEYS'),
    ],

    'limits' => [
        'max_cores' => (int) env('VM_MAX_CORES', 16),
        'max_memory' => (int) env('VM_MAX_MEMORY', 65536),
        'max_disk' => (int) env('VM_MAX_DISK', 500),
    ],

    'catalog' => VmCatalog::defaults(),
];
