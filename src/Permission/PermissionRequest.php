<?php

declare(strict_types=1);

namespace Develate\GrokCli\Permission;

final readonly class PermissionRequest
{
    /**
     * @param  list<PermissionOption>  $options
     * @param  array<string, mixed>  $toolCall
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $sessionId,
        public array $options,
        public array $toolCall,
        public mixed $id,
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function fromParams(mixed $id, array $params): self
    {
        $options = [];
        foreach ($params['options'] ?? [] as $option) {
            if (is_array($option)) {
                $options[] = PermissionOption::fromArray($option);
            }
        }

        $toolCall = is_array($params['toolCall'] ?? null) ? $params['toolCall'] : [];

        return new self(
            sessionId: (string) ($params['sessionId'] ?? ''),
            options: $options,
            toolCall: $toolCall,
            id: $id,
            raw: $params,
        );
    }

    public function toolName(): string
    {
        foreach (['toolName', 'title', 'kind'] as $key) {
            if (isset($this->toolCall[$key]) && is_string($this->toolCall[$key]) && $this->toolCall[$key] !== '') {
                return $this->toolCall[$key];
            }
        }

        return 'tool';
    }

    /**
     * @return array<string, mixed>
     */
    public function input(): array
    {
        $input = $this->toolCall['rawInput'] ?? $this->toolCall['input'] ?? [];

        return is_array($input) ? $input : [];
    }

    public function allowOnce(): ?PermissionOption
    {
        foreach ($this->options as $option) {
            if ($option->isAllow()) {
                return $option;
            }
        }

        return $this->options[0] ?? null;
    }

    public function rejectOnce(): ?PermissionOption
    {
        foreach ($this->options as $option) {
            if ($option->isReject()) {
                return $option;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
