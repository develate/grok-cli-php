<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

use Develate\GrokCli\Tool\ToolCallStatus;

final readonly class ToolCallUpdate implements SessionUpdate
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?ToolCallStatus $status,
        public mixed $output,
        private array $raw = [],
    ) {
    }

    public function type(): string
    {
        return 'tool_call_update';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
