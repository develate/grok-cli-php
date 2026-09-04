<?php

declare(strict_types=1);

namespace Develate\GrokCli\Permission;

final readonly class PermissionDecision
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public static function allow(?PermissionOption $option = null): self
    {
        if ($option === null) {
            return new self(['outcome' => ['outcome' => 'selected', 'optionId' => 'allow-once']]);
        }

        return new self(['outcome' => ['outcome' => 'selected', 'optionId' => $option->optionId]]);
    }

    public static function deny(?PermissionOption $option = null): self
    {
        if ($option === null) {
            return new self(['outcome' => ['outcome' => 'cancelled']]);
        }

        return new self(['outcome' => ['outcome' => 'selected', 'optionId' => $option->optionId]]);
    }

    public static function cancelled(): self
    {
        return new self(['outcome' => ['outcome' => 'cancelled']]);
    }

    public static function selected(string $optionId): self
    {
        return new self(['outcome' => ['outcome' => 'selected', 'optionId' => $optionId]]);
    }
}
