<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tool;

final readonly class Command
{
    public function __construct(
        public string $toolCallId,
        public string $command,
        public ?string $output = null,
        public ?int $exitCode = null,
    ) {
    }
}
