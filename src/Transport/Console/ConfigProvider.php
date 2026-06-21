<?php

declare(strict_types=1);

namespace Componenta\CQRS\App\Transport\Console;

use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getAutowires(): array
    {
        return [
            WorkerCommand::class,
        ];
    }
}
