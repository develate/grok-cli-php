<?php

declare(strict_types=1);

namespace Develate\GrokCli\Process;

use Develate\GrokCli\Exception\GrokNotFound;
use Symfony\Component\Process\ExecutableFinder;

final class GrokExecutable
{
    public static function assertAvailable(string $binary): void
    {
        if (self::resolve($binary) === null) {
            throw new GrokNotFound(sprintf('Grok Build binary "%s" was not found.', $binary));
        }
    }

    public static function resolve(string $binary): ?string
    {
        if ($binary !== '' && (is_file($binary) || str_contains($binary, DIRECTORY_SEPARATOR))) {
            return is_file($binary) && is_executable($binary) ? $binary : null;
        }

        $found = (new ExecutableFinder)->find($binary);

        return is_string($found) && $found !== '' ? $found : null;
    }
}
