<?php

declare(strict_types=1);

namespace Develate\GrokCli\Process;

use Develate\GrokCli\Exception\GrokNotFound;
use Develate\GrokCli\Exception\ProcessCancelled;
use Develate\GrokCli\Exception\ProcessTimedOut;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOut;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

final class GrokProcess
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly array $command,
        private readonly ?string $cwd = null,
        private readonly ?float $timeout = null,
        private readonly array $env = [],
        private readonly ?string $input = null,
    ) {
    }

    /** @return \Generator<int, string, void, ProcessResult> */
    public function lines(?\Closure $isCancelled = null): \Generator
    {
        GrokExecutable::assertAvailable($this->command[0]);

        try {
            $process = new Process($this->command, $this->cwd, $this->env, $this->input, $this->timeout);
            $process->start();
        } catch (SymfonyRuntimeException $exception) {
            throw new GrokNotFound(sprintf('Grok Build binary "%s" was not found.', $this->command[0]), 0, $exception);
        }

        $buffer = '';
        $stderr = '';

        try {
            do {
                if ($isCancelled !== null && $isCancelled()) {
                    $process->stop(0.2);
                    throw new ProcessCancelled('Grok Build run was cancelled.');
                }
                $process->checkTimeout();
                $buffer .= $process->getIncrementalOutput();
                $stderr .= $process->getIncrementalErrorOutput();
                [$lines, $buffer] = self::split($buffer);
                foreach ($lines as $line) {
                    yield $line;
                }
                if ($process->isRunning()) {
                    usleep(10_000);
                }
            } while ($process->isRunning());

            $buffer .= $process->getIncrementalOutput();
            $stderr .= $process->getIncrementalErrorOutput();
            [$lines, $buffer] = self::split($buffer);
            foreach ($lines as $line) {
                yield $line;
            }
            if (trim($buffer) !== '') {
                yield $buffer;
            }
        } catch (SymfonyProcessTimedOut $exception) {
            throw new ProcessTimedOut($exception->getMessage(), 0, $exception);
        } finally {
            if ($process->isRunning()) {
                $process->stop(0.2);
            }
        }

        return new ProcessResult($process->getExitCode() ?? 1, $stderr, $process->getOutput());
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private static function split(string $buffer): array
    {
        $lines = [];
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            if (str_ends_with($line, "\r")) {
                $line = substr($line, 0, -1);
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return [$lines, $buffer];
    }
}
