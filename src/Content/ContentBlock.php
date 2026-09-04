<?php

declare(strict_types=1);

namespace Develate\GrokCli\Content;

interface ContentBlock
{
    /** @return array<string, mixed> */
    public function raw(): array;

    /** @return array<string, mixed> */
    public function toPrompt(): array;
}
