<?php

declare(strict_types=1);

use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;
use Componenta\CQRS\App\Transport\Console\ConfigProvider;

it('registers the worker command through the console config contract', function (): void {
    $config = (new ConfigProvider())();

    expect($config[ConsoleConfigKey::COMMANDS])->toBe([
        WorkerCommand::class,
    ])
        ->and($config[DependencyConfigKey::DEPENDENCIES])
        ->not->toHaveKey('autowires');
});
