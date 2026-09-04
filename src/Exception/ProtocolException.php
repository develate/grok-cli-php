<?php

declare(strict_types=1);

namespace Develate\GrokCli\Exception;

final class ProtocolException extends GrokException
{
    public function __construct(
        string $message,
        public readonly ?int $rpcCode = null,
        public readonly mixed $data = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
