<?php

declare(strict_types=1);

namespace Develate\GrokCli\Content;

final readonly class UnknownBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $type,
        private array $raw = [],
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function toPrompt(): array
    {
        return $this->raw !== [] ? $this->raw : ['type' => $this->type];
    }
}
