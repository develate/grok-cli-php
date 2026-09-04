<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

interface SessionUpdate
{
    public function type(): string;

    /** @return array<string, mixed> */
    public function raw(): array;
}
