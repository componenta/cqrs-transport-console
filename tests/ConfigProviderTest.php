<?php

declare(strict_types=1);

use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;
use Componenta\CQRS\App\Transport\Console\ConfigProvider;

it('registers worker command autowire', function (): void {
    $config = (new ConfigProvider())();
    $autowires = $config[DependencyConfigKey::DEPENDENCIES][DependencyConfigKey::AUTOWIRES];

    expect($autowires)->toContain(WorkerCommand::class);
});
