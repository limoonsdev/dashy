<?php

test('generate_application_name strips owner from git repository', function () {
    $name = generate_application_name('dashyapp/dashy', 'main', 'test123');

    expect($name)->toBe('dashy:main-test123');
    expect($name)->not->toContain('coollabsio');
});

test('generate_application_name handles repository without owner', function () {
    $name = generate_application_name('dashy', 'main', 'test123');

    expect($name)->toBe('dashy:main-test123');
});

test('generate_application_name handles deeply nested repository path', function () {
    $name = generate_application_name('org/sub/repo-name', 'develop', 'abc456');

    expect($name)->toBe('repo-name:develop-abc456');
    expect($name)->not->toContain('org');
    expect($name)->not->toContain('sub');
});
