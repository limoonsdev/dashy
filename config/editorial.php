<?php

/*
|--------------------------------------------------------------------------
| Editorial connectors
|--------------------------------------------------------------------------
|
| Every channel is configured with environment variables, so publishing from
| the editorial workspace never requires editing code. A channel without its
| required value is shown as "not configured" and is skipped at publish time.
|
*/

return [
    'channels' => [
        'webhook' => [
            'label' => 'Webhook',
            'icon' => 'link',
            'description' => 'POST the post as JSON to any endpoint.',
            'url' => env('EDITORIAL_WEBHOOK_URL'),
        ],
        'slack' => [
            'label' => 'Slack',
            'icon' => 'slack',
            'description' => 'Send the title and excerpt to a Slack incoming webhook.',
            'url' => env('EDITORIAL_SLACK_WEBHOOK_URL'),
        ],
        'discord' => [
            'label' => 'Discord',
            'icon' => 'discord',
            'description' => 'Post an embed to a Discord channel webhook.',
            'url' => env('EDITORIAL_DISCORD_WEBHOOK_URL'),
        ],
        'wordpress' => [
            'label' => 'WordPress',
            'icon' => 'wordpress',
            'description' => 'Create a post through the WordPress REST API.',
            'url' => env('EDITORIAL_WORDPRESS_URL'),
            'username' => env('EDITORIAL_WORDPRESS_USERNAME'),
            'password' => env('EDITORIAL_WORDPRESS_APP_PASSWORD'),
        ],
        'ghost' => [
            'label' => 'Ghost',
            'icon' => 'ghost',
            'description' => 'Create a published post through the Ghost Admin API.',
            'url' => env('EDITORIAL_GHOST_URL'),
            'admin_key' => env('EDITORIAL_GHOST_ADMIN_KEY'),
        ],
        'github' => [
            'label' => 'GitHub',
            'icon' => 'github',
            'description' => 'Open an issue with the post, useful for changelogs and docs.',
            'token' => env('EDITORIAL_GITHUB_TOKEN'),
            'repository' => env('EDITORIAL_GITHUB_REPOSITORY'),
        ],
        'notion' => [
            'label' => 'Notion',
            'icon' => 'notion',
            'description' => 'Create a page in a Notion database.',
            'token' => env('EDITORIAL_NOTION_TOKEN'),
            'database_id' => env('EDITORIAL_NOTION_DATABASE_ID'),
        ],
    ],
];
