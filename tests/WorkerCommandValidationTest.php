<?php

declare(strict_types=1);

use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Transport\CommandSerializerInterface;
use Componenta\CQRS\Command\Transport\OperationContextSerializerInterface;
use Componenta\CQRS\Command\Transport\TransportRegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('rejects negative worker limits before resolving a transport', function (string $option): void {
    $transports = $this->createMock(TransportRegistryInterface::class);
    $transports->expects($this->never())->method('has');
    $command = new WorkerCommand(
        $this->createStub(CommandBusInterface::class),
        $this->createStub(CommandSerializerInterface::class),
        $this->createStub(OperationContextSerializerInterface::class),
        $transports,
    );
    $tester = new CommandTester($command);

    $status = $tester->execute([$option => '-1']);

    expect($status)->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('must be non-negative');
})->with([
    'sleep' => ['--sleep'],
    'command limit' => ['--limit'],
    'time limit' => ['--time-limit'],
    'memory limit' => ['--memory-limit'],
]);

it('rejects an overflowing memory limit and malformed numeric options before transport lookup', function (string $option, string $value, string $diagnostic): void {
    $registry = $this->createMock(TransportRegistryInterface::class);
    $registry->expects($this->never())->method('has');
    $tester = new CommandTester(new WorkerCommand(
        $this->createStub(CommandBusInterface::class),
        $this->createStub(CommandSerializerInterface::class),
        $this->createStub(OperationContextSerializerInterface::class),
        $registry,
    ));

    expect($tester->execute([$option => $value]))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain($diagnostic);
})->with([
    ['--memory-limit', (string) PHP_INT_MAX, 'too large'],
    ['--limit', (string) PHP_INT_MAX . '0', 'outside the supported integer range'],
    ['--sleep', '0.5', 'must be an integer'],
]);
