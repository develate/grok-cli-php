<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class ModelInfo
{
    /**
     * @param  list<ReasoningEffort>  $reasoningEfforts
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public ?int $contextTokens = null,
        public bool $supportsReasoningEffort = false,
        public ?ReasoningEffort $defaultReasoningEffort = null,
        public array $reasoningEfforts = [],
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['modelId'] ?? $data['id'] ?? '');
        $meta = is_array($data['_meta'] ?? null) ? $data['_meta'] : [];
        $efforts = [];
        foreach ($meta['reasoningEfforts'] ?? [] as $effort) {
            if (! is_array($effort)) {
                continue;
            }
            $value = $effort['value'] ?? $effort['id'] ?? null;
            $parsed = is_string($value) ? ReasoningEffort::tryFrom($value) : null;
            if ($parsed !== null) {
                $efforts[] = $parsed;
            }
        }

        $default = $meta['reasoningEffort'] ?? null;

        return new self(
            id: $id,
            name: (string) ($data['name'] ?? $id),
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            contextTokens: isset($meta['totalContextTokens']) && is_numeric($meta['totalContextTokens'])
                ? (int) $meta['totalContextTokens']
                : null,
            supportsReasoningEffort: (bool) ($meta['supportsReasoningEffort'] ?? $efforts !== []),
            defaultReasoningEffort: is_string($default) ? ReasoningEffort::tryFrom($default) : null,
            reasoningEfforts: $efforts,
            raw: $data,
        );
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
