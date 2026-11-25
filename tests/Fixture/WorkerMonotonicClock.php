<?php

declare(strict_types=1);

namespace Componenta\CQRS\App\Command\Transport\Console;

/** Test-only clock; ordinary tests use the real monotonic clock. */
final class WorkerMonotonicClockProbe
{
    /** @var list<array{int, int}>|null */
    public static ?array $readings = null;
}

function hrtime(bool $as_number = false): array|int|float|false
{
    if (WorkerMonotonicClockProbe::$readings === null) {
        return \hrtime($as_number);
    }
    $reading = array_shift(WorkerMonotonicClockProbe::$readings)
        ?? throw new \LogicException('Unexpected monotonic clock read.');

    return $as_number ? $reading[0] * 1_000_000_000 + $reading[1] : $reading;
}
