<?php

declare(strict_types=1);

namespace Componenta\CQRS\App\Transport\Console;

use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;

final class ConfigProvider extends BaseConfigProvider
{
    /**
     * @return array<string, list<class-string>>
     */
    protected function getConfig(): array
    {
        return [
            ConsoleConfigKey::COMMANDS => [
                WorkerCommand::class,
            ],
        ];
    }
}
