<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tool;

final readonly class Plan
{
    /**
     * @param  list<PlanEntry>  $entries
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $entries,
        private array $raw = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
