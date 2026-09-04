<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

final readonly class UserMessageChunk implements SessionUpdate
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $text,
        private array $raw = [],
    ) {
    }

    public function type(): string
    {
        return 'user_message_chunk';
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
