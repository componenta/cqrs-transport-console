<?php

declare(strict_types=1);

namespace Componenta\CQRS\App\Command\Transport\Console;

final class WorkerIdleSleepProbe
{
    public static ?\Closure $sleep = null;
}

function sleep(int $seconds): int|false
{
    if (WorkerIdleSleepProbe::$sleep !== null) {
        (WorkerIdleSleepProbe::$sleep)($seconds);
        return 0;
    }

    return \sleep($seconds);
}
