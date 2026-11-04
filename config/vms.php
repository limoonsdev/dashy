<?php

use App\Services\Vms\VmCatalog;

return [
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
        'verify_tls' => env('PROXMOX_VERIFY_TLS', true),
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
