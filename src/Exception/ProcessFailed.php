<?php

declare(strict_types=1);

namespace Develate\GrokCli\Exception;

final class ProcessFailed extends GrokException
{
    public function __construct(
        public readonly int $exitCode,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
