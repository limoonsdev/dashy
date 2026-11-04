<?php

use App\Models\Application;

it('generates commit links for direct repository remotes', function (string $repository, string $expected) {
    $application = new Application;
    $application->setRelation('source', null);
    $application->git_repository = $repository;

    expect($application->gitCommitLink('1234567890abcdef'))->toBe($expected);
})->with([
    'HTTPS remote' => [
        'https://github.com/dashyapp/dashy.git',
        'https://github.com/dashyapp/dashy/commit/1234567890abcdef',
    ],
    'SSH remote' => [
        'git@github.com:dashyapp/dashy.git',
        'https://github.com/dashyapp/dashy/commit/1234567890abcdef',
    ],
    'SSH remote with custom username' => [
        'custom-user@git.example.com:dashyapp/dashy.git',
        'https://git.example.com/dashyapp/dashy/commit/1234567890abcdef',
    ],
    'SSH remote with custom username and port' => [
        'custom-user@git.example.com:2222/dashyapp/dashy.git',
        'https://git.example.com/dashyapp/dashy/commit/1234567890abcdef',
    ],
    'SSH URL' => [
        'ssh://git@gitlab.com/dashyapp/dashy.git',
        'https://gitlab.com/dashyapp/dashy/commit/1234567890abcdef',
    ],
    'Bitbucket HTTPS remote' => [
        'https://bitbucket.org/dashyapp/dashy.git',
        'https://bitbucket.org/dashyapp/dashy/commits/1234567890abcdef',
    ],
]);

it('does not generate commit links from incomplete repository URLs', function (string $repository) {
    $application = new Application;
    $application->setRelation('source', null);
    $application->git_repository = $repository;

    expect($application->gitCommitLink('1234567890abcdef'))->toBeNull();
})->with([
    'missing host' => 'https://',
    'missing scheme' => 'github.com/dashyapp/dashy',
    'git protocol' => 'git://git.example.com/dashyapp/dashy.git',
]);

it('converts ssh remotes with generic usernames into https repository links', function (string $repository, string $expectedBranch, string $expectedCommits, string $expectedWebhook) {
    $application = new Application;
    $application->setRelation('source', null);
    $application->git_repository = $repository;
    $application->git_branch = 'main';
    $application->base_directory = '/';

    expect($application->gitBranchLocation)->toBe($expectedBranch)
        ->and($application->gitCommits)->toBe($expectedCommits)
        ->and($application->gitWebhook)->toBe($expectedWebhook);
})->with([
    'git username' => [
        'git@github.com:dashyapp/dashy.git',
        'https://github.com/dashyapp/dashy/tree/main/',
        'https://github.com/dashyapp/dashy/commits/main',
        'https://github.com/dashyapp/dashy/settings/hooks',
    ],
    'custom username' => [
        'custom-user@git.example.com:organization/repository.git',
        'https://git.example.com/organization/repository/tree/main/',
        'https://git.example.com/organization/repository/commits/main',
        'https://git.example.com/organization/repository/settings/hooks',
    ],
    'custom username and port' => [
        'custom-user@git.example.com:2222/organization/repository.git',
        'https://git.example.com/organization/repository/tree/main/',
        'https://git.example.com/organization/repository/commits/main',
        'https://git.example.com/organization/repository/settings/hooks',
    ],
    'ssh scheme with port' => [
        'ssh://git@gitea.example.com:2222/organization/repository.git',
        'https://gitea.example.com/organization/repository/tree/main/',
        'https://gitea.example.com/organization/repository/commits/main',
        'https://gitea.example.com/organization/repository/settings/hooks',
    ],
]);
