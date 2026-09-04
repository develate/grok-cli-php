<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class RunMetadata
{
    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public string $grokVersion,
        public string $transport,
        public ?string $requestedModel,
        public string $cwd,
        public ?string $requestId = null,
        public ?int $protocolVersion = null,
        public array $capabilities = [],
    ) {
    }
}
