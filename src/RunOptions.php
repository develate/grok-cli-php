<?php

declare(strict_types=1);

namespace Develate\GrokCli;

final readonly class RunOptions
{
    /**
     * @param  list<string>  $images
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        public array $images = [],
        public ?int $maxTurns = null,
        public ?array $schema = null,
        public ?float $timeout = null,
    ) {
    }
}
