<?php

use Illuminate\Log\LogManager;
use Monolog\Handler\TestHandler;

it('redacts secrets when Laravel creates a configured log channel', function (string $channel) {
    config(['logging.redaction_enabled' => true, 'constants.dashy.self_hosted' => true]);
    $configuration = config("logging.channels.{$channel}");
    $configuration['driver'] = 'monolog';
    $configuration['handler'] = TestHandler::class;
    config([
        "logging.channels.{$channel}" => $configuration,
        'logging.channels.emergency.path' => '/dev/null',
    ]);
    $logger = (new LogManager(app()))->channel($channel);
    $handler = $logger->getLogger()->getHandlers()[0];

    expect($handler)->toBeInstanceOf(TestHandler::class);

    $logger->error('password=dashy-canary-channel-secret', [
        'token' => 'dashy-canary-context-secret',
        'user_email' => 'admin@example.com',
    ]);

    $record = $handler->getRecords()[0];

    expect($record->message)->toBe('password='.REDACTED);
    expect($record->context['token'])->toBe(REDACTED);
    expect($record->context['user_email'])->toBe($channel === 'audit' ? 'admin@example.com' : REDACTED);
})->with(['single', 'audit']);
