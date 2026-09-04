<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

final readonly class UnknownUpdate implements SessionUpdate
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $type,
        private array $raw = [],
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
