<?php

declare(strict_types=1);

use Componenta\CQRS\App\Command\Transport\Console\WorkerCommand;
use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\OperationInterface;
use Componenta\CQRS\Command\Transport\CommandSerializerInterface;
use Componenta\CQRS\Command\Transport\Envelope;
use Componenta\CQRS\Command\Transport\JsonOperationContextSerializer;
use Componenta\CQRS\Command\Transport\TransportInterface;
use Componenta\CQRS\Command\Transport\TransportRegistryInterface;
use Componenta\CQRS\Map\CqrsMap;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function compatibilityWorkerTester(): CommandTester
{
    $transport = new readonly class implements TransportInterface {
        public function send(Envelope $envelope, int $delay = 0): Envelope
        {
            return $envelope;
        }

        public function get(): ?Envelope
        {
            throw new RuntimeException('The zero limit must exit before polling transport.');
        }

        public function ack(Envelope $envelope): void {}
        public function reject(Envelope $envelope): void {}
    };

    $registry = new readonly class($transport) implements TransportRegistryInterface {
        public function __construct(private TransportInterface $transport) {}
        public function get(string $name): TransportInterface { return $this->transport; }
        public function has(string $name): bool { return $name === 'default'; }
    };

    $bus = new readonly class implements CommandBusInterface {
        public function dispatch(object $command, array $attributes = []): OperationInterface
        {
            throw new RuntimeException('A zero limit must not dispatch commands.');
        }
    };

    $serializer = new readonly class implements CommandSerializerInterface {
        public function serialize(object $command): string { return '{}'; }
        public function deserialize(string $payload, string $commandClass): object
        {
            throw new RuntimeException('A zero limit must not deserialize commands.');
        }
    };

    $commands = new readonly class implements CqrsMapProviderInterface {
        public function map(): CqrsMap { return new CqrsMap(); }
    };

    return new CommandTester(new WorkerCommand(
        $bus,
        $serializer,
        new JsonOperationContextSerializer(),
        $registry,
        $commands,
    ));
}

it('constructs the safe transport worker before a zero command limit exits', function (): void {
    $tester = compatibilityWorkerTester();

    $status = $tester->execute([
        'transport' => 'default',
        '--limit' => '0',
        '--sleep' => '0',
    ]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('Worker started')
        ->and($tester->getDisplay())->toContain('Limit reached: 0 commands');
});

it('uses the monotonic runtime clock before a zero time limit exits', function (): void {
    $tester = compatibilityWorkerTester();

    $status = $tester->execute([
        'transport' => 'default',
        '--time-limit' => '0',
        '--sleep' => '0',
    ]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('Worker started')
        ->and($tester->getDisplay())->toContain('Time limit reached: 0 seconds');
});
