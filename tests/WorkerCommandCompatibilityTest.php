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
use Componenta\CQRS\Command\Metadata\CommandMetadataProviderInterface;
use Componenta\CQRS\Command\Middleware\TransportMiddleware;
use Componenta\CQRS\Command\Operation;
use Componenta\CQRS\Command\Transport\CommandWorker;
use Componenta\CQRS\Command\Transport\ExecutionMode;
use Componenta\CQRS\Command\Transport\TransportRegistry;
use Componenta\CQRS\Transport\Attribute\Async;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Componenta\CQRS\App\Command\Transport\Console\WorkerMonotonicClockProbe;
use Componenta\CQRS\App\Command\Transport\Console\WorkerIdleSleepProbe;

require_once __DIR__ . '/Fixture/WorkerMonotonicClock.php';
require_once __DIR__ . '/Fixture/WorkerIdleSleep.php';

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

    return new CommandTester(new WorkerCommand(
        $bus,
        $serializer,
        new JsonOperationContextSerializer(),
        $registry,
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

it('processes a delivery through the injected command metadata provider without a handler map', function (): void {
    $envelope = new Envelope('producer-id', stdClass::class, '{}');
    $transport = new class ($envelope) implements TransportInterface {
        public int $acks = 0;
        public int $rejects = 0;
        public function __construct(private ?Envelope $envelope) {}
        public function send(Envelope $envelope, int $delay = 0): Envelope { return $envelope; }
        public function get(): ?Envelope
        {
            $envelope = $this->envelope;
            $this->envelope = null;
            return $envelope;
        }
        public function ack(Envelope $envelope): void { ++$this->acks; }
        public function reject(Envelope $envelope): void { ++$this->rejects; }
    };
    $registry = new TransportRegistry();
    $registry->register('default', $transport);
    $bus = new class implements CommandBusInterface {
        public array $operations = [];
        public function dispatch(object $command, array $attributes = []): OperationInterface
        {
            $operation = Operation::create($command, $attributes);
            $this->operations[] = $operation;
            return $operation;
        }
    };
    $serializer = new readonly class implements CommandSerializerInterface {
        public function serialize(object $command): string { return '{}'; }
        public function deserialize(string $payload, string $commandClass): object { return new stdClass(); }
    };
    $metadata = new class implements CommandMetadataProviderInterface {
        public array $lookups = [];
        public function get(object|string $command, string $attribute): ?object
        {
            $this->lookups[] = [$command, $attribute];
            return $attribute === Async::class ? new Async('default') : null;
        }
    };

    $tester = new CommandTester(new WorkerCommand(
        $bus,
        $serializer,
        new JsonOperationContextSerializer(),
        $registry,
        $metadata,
    ));
    $status = $tester->execute(['--limit' => '1', '--sleep' => '0']);

    expect($status)->toBe(Command::SUCCESS)
        ->and($transport->acks)->toBe(1)
        ->and($transport->rejects)->toBe(0)
        ->and($bus->operations)->toHaveCount(1)
        ->and($metadata->lookups)->toBe([[$bus->operations[0]->command, Async::class]])
        ->and($bus->operations[0]->attributes[CommandWorker::ATTR_ORIGINAL_OPERATION_ID])->toBe('producer-id')
        ->and($bus->operations[0]->attributes[TransportMiddleware::ATTR_EXECUTION_MODE])->toBe(ExecutionMode::SYNC);
});

it('does not expire a one-second runtime limit at an earlier integer-second boundary', function (): void {
    $transport = $this->createMock(TransportInterface::class);
    $transport->expects($this->once())->method('get')->willReturn(null);
    $registry = new TransportRegistry();
    $registry->register('default', $transport);
    $tester = new CommandTester(new WorkerCommand(
        $this->createStub(CommandBusInterface::class),
        $this->createStub(CommandSerializerInterface::class),
        new JsonOperationContextSerializer(),
        $registry,
    ));

    WorkerMonotonicClockProbe::$readings = [
        [100, 900_000_000], // start
        [101, 100_000_000], // 0.2 seconds elapsed: still poll
        [101, 900_000_000], // 1.0 seconds elapsed: exit
    ];
    try {
        $status = $tester->execute(['--time-limit' => '1', '--sleep' => '0']);

        expect($status)->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toContain('Time limit reached: 1 seconds')
            ->and(WorkerMonotonicClockProbe::$readings)->toBe([]);
    } finally {
        WorkerMonotonicClockProbe::$readings = null;
    }
});


it('honors a stop signal received during an empty poll without another idle wait', function (): void {
    $command = null;
    $waits = [];
    $transport = $this->createMock(TransportInterface::class);
    $transport->expects($this->once())->method('get')->willReturnCallback(
        static function () use (&$command): ?Envelope {
            expect($command->handleSignal(15))->toBeFalse();
            return null;
        },
    );
    $transport->expects($this->never())->method('ack');
    $transport->expects($this->never())->method('reject');
    $registry = new TransportRegistry();
    $registry->register('default', $transport);
    $bus = $this->createMock(CommandBusInterface::class);
    $bus->expects($this->never())->method('dispatch');
    $command = new WorkerCommand(
        $bus,
        $this->createStub(CommandSerializerInterface::class),
        new JsonOperationContextSerializer(),
        $registry,
    );
    $tester = new CommandTester($command);
    WorkerIdleSleepProbe::$sleep = static function (int $seconds) use (&$waits): void {
        $waits[] = $seconds;
    };
    try {
        expect($tester->execute(['--sleep' => '5']))->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toContain('Worker stopped by signal')
            ->and($waits)->toBe([]);
    } finally {
        WorkerIdleSleepProbe::$sleep = null;
    }
});
