<?php

declare(strict_types=1);

use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;
use Symfony\Component\Console\Attribute\AsCommand;

it('exposes the worker command through Symfony command discovery', function (): void {
    $attributes = (new ReflectionClass(WorkerCommand::class))
        ->getAttributes(AsCommand::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->getArguments()['name'])
        ->toBe('cqrs:worker');
});
