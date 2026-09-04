<?php

declare(strict_types=1);

namespace Develate\GrokCli\Permission;

final readonly class PermissionOption
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $optionId,
        public string $name,
        public ?string $kind = null,
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            optionId: (string) ($data['optionId'] ?? $data['id'] ?? ''),
            name: (string) ($data['name'] ?? $data['label'] ?? ''),
            kind: isset($data['kind']) && is_string($data['kind']) ? $data['kind'] : null,
            raw: $data,
        );
    }

    public function isAllow(): bool
    {
        $kind = strtolower((string) $this->kind);
        $name = strtolower($this->name);

        return str_contains($kind, 'allow') || str_contains($name, 'allow');
    }

    public function isReject(): bool
    {
        $kind = strtolower((string) $this->kind);
        $name = strtolower($this->name);

        return str_contains($kind, 'reject') || str_contains($kind, 'deny')
            || str_contains($name, 'reject') || str_contains($name, 'deny');
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
