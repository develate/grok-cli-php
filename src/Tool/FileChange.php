<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tool;

use Develate\GrokCli\Value\FileChangeKind;

final readonly class FileChange
{
    public function __construct(
        public string $toolCallId,
        public string $path,
        public FileChangeKind $kind,
    ) {
    }
}
