<?php

declare(strict_types=1);

namespace Develate\GrokCli\Process;

class ProcessFactory
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    public function create(
        array $command,
        ?string $cwd = null,
        ?float $timeout = null,
        array $env = [],
        ?string $input = null,
    ): GrokProcess {
        return new GrokProcess($command, $cwd, $timeout, $env, $input);
    }
}
