<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tool;

final readonly class PlanEntry
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $content,
        public ?string $status = null,
        public ?string $priority = null,
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: (string) ($data['content'] ?? $data['text'] ?? ''),
            status: isset($data['status']) && is_string($data['status']) ? $data['status'] : null,
            priority: isset($data['priority']) && is_string($data['priority']) ? $data['priority'] : null,
            raw: $data,
        );
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
