<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

use Develate\GrokCli\Tool\Plan;
use Develate\GrokCli\Tool\PlanEntry;

final readonly class PlanUpdate implements SessionUpdate
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

    public function type(): string
    {
        return 'plan';
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function plan(): Plan
    {
        return new Plan($this->entries, $this->raw);
    }
}
