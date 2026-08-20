<?php

declare(strict_types=1);

namespace Componenta\CQRS\App\Command\Transport\Console;

use Componenta\CQRS\Command\CommandBusInterface;
use Componenta\CQRS\Command\Transport\CommandSerializerInterface;
use Componenta\CQRS\Command\Transport\CommandWorker;
use Componenta\CQRS\Command\Transport\OperationContextSerializerInterface;
use Componenta\CQRS\Command\Transport\TransportRegistryInterface;
use Componenta\CQRS\Map\CqrsMapProviderInterface;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Console command to process CQRS commands from a registered transport. */
#[AsCommand(
    name: 'cqrs:worker',
    description: 'Process commands from transport',
)]
final class WorkerCommand extends Command implements SignalableCommandInterface
{
    private ?CommandWorker $worker = null;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly CommandBusInterface $bus,
        private readonly CommandSerializerInterface $serializer,
        private readonly OperationContextSerializerInterface $contextSerializer,
        private readonly TransportRegistryInterface $transports,
        private readonly CqrsMapProviderInterface $commands,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
        $this->logger = $logger ?? new NullLogger();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'transport',
                InputArgument::OPTIONAL,
                'Transport name',
                'default',
            )
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_REQUIRED,
                'Seconds to sleep when idle',
                1,
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of commands to process',
            )
            ->addOption(
                'time-limit',
                't',
                InputOption::VALUE_REQUIRED,
                'Maximum runtime in seconds',
            )
            ->addOption(
                'memory-limit',
                'm',
                InputOption::VALUE_REQUIRED,
                'Maximum memory in MB',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        set_time_limit(0);

        $io = new SymfonyStyle($input, $output);

        $transportName = $input->getArgument('transport');
        if (!is_string($transportName) || trim($transportName) === '') {
            $io->error('Transport name must be a non-empty string.');
            return Command::FAILURE;
        }

        try {
            $sleep = self::nonNegativeInteger($input->getOption('sleep'), 'sleep');
            if ($sleep === null) {
                throw new InvalidArgumentException('Option "sleep" is required.');
            }

            $limit = self::nonNegativeInteger($input->getOption('limit'), 'limit', optional: true);
            $timeLimit = self::nonNegativeInteger($input->getOption('time-limit'), 'time-limit', optional: true);
            $memoryLimitMb = self::nonNegativeInteger($input->getOption('memory-limit'), 'memory-limit', optional: true);

            if ($memoryLimitMb !== null
                && $memoryLimitMb > intdiv(PHP_INT_MAX, 1024 * 1024)
            ) {
                throw new InvalidArgumentException(
                    'Option "memory-limit" is too large.',
                );
            }

            $memoryLimit = $memoryLimitMb === null
                ? null
                : $memoryLimitMb * 1024 * 1024;
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (!$this->transports->has($transportName)) {
            $io->error("Transport '{$transportName}' is not registered");
            return Command::FAILURE;
        }

        $transport = $this->transports->get($transportName);

        $this->worker = new CommandWorker(
            bus: $this->bus,
            serializer: $this->serializer,
            contextSerializer: $this->contextSerializer,
            transport: $transport,
            transportName: $transportName,
            commands: $this->commands,
            logger: $this->logger,
        );

        $io->success("Worker started for transport '{$transportName}'");

        if ($limit !== null) {
            $io->info("Limit: {$limit} commands");
        }
        if ($timeLimit !== null) {
            $io->info("Time limit: {$timeLimit} seconds");
        }
        if ($memoryLimit !== null) {
            $io->info('Memory limit: ' . ($memoryLimit / 1024 / 1024) . ' MB');
        }

        $startTime = hrtime()[0];
        $processed = 0;

        while (true) {
            $worker = $this->activeWorker();

            if ($worker === null) {
                $io->info('Worker stopped by signal');
                break;
            }

            if ($limit !== null && $processed >= $limit) {
                $io->info("Limit reached: $processed commands");
                break;
            }

            if ($timeLimit !== null && (hrtime()[0] - $startTime) >= $timeLimit) {
                $io->info("Time limit reached: $timeLimit seconds");
                break;
            }

            if ($memoryLimit !== null && memory_get_usage(true) >= $memoryLimit) {
                $io->info('Memory limit reached: ' . round(memory_get_usage(true) / 1024 / 1024) . ' MB');
                break;
            }

            if ($worker->processOne()) {
                ++$processed;
                if ($output->isVerbose()) {
                    $io->writeln("Processed: $processed");
                }
            } else {
                sleep($sleep);
            }
        }

        $io->success("Worker finished. Processed: {$processed} commands");

        return Command::SUCCESS;
    }

    private static function nonNegativeInteger(
        mixed $value,
        string $name,
        bool $optional = false,
    ): ?int {
        if ($value === null) {
            if ($optional) {
                return null;
            }

            throw new InvalidArgumentException(sprintf('Option "%s" is required.', $name));
        }

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);

            if ($integer === false) {
                throw new InvalidArgumentException(sprintf(
                    'Option "%s" is outside the supported integer range.',
                    $name,
                ));
            }
        } else {
            throw new InvalidArgumentException(sprintf(
                'Option "%s" must be an integer.',
                $name,
            ));
        }

        if ($integer < 0) {
            throw new InvalidArgumentException(sprintf(
                'Option "%s" must be non-negative.',
                $name,
            ));
        }

        return $integer;
    }

    /** @phpstan-impure */
    private function activeWorker(): ?CommandWorker
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        return $this->worker;
    }

    /** @return list<int> */
    #[Override]
    public function getSubscribedSignals(): array
    {
        if (!\defined('SIGINT')) {
            return [];
        }

        return [SIGINT, SIGTERM];
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->worker?->stop();
        $this->worker = null;

        return false;
    }
}
