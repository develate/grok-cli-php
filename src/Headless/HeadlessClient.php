<?php

declare(strict_types=1);

namespace Develate\GrokCli\Headless;

use Develate\GrokCli\Exception\ProcessFailed;
use Develate\GrokCli\Process\ProcessFactory;
use Develate\GrokCli\Process\ProcessResult;
use Develate\GrokCli\Prompt;
use Develate\GrokCli\Result;
use Develate\GrokCli\ResultBuilder;
use Develate\GrokCli\RunOptions;
use Develate\GrokCli\SessionOptions;
use Develate\GrokCli\Update\SessionUpdate;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\SandboxProfile;
use Develate\GrokCli\Value\UsageSource;

final class HeadlessClient
{
    /**
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly string $binary,
        private readonly array $env = [],
        private readonly ?float $timeout = null,
        private readonly ProcessFactory $processes = new ProcessFactory,
        private readonly ArgumentBuilder $arguments = new ArgumentBuilder,
        private readonly MessagesStreamParser $parser = new MessagesStreamParser,
        private readonly string $grokVersion = '',
    ) {}

    /**
     * @return \Generator<int, SessionUpdate, void, Result>
     */
    public function stream(
        Prompt $prompt,
        SessionOptions $session,
        ?RunOptions $run = null,
        ?string $resumeSessionId = null,
    ): \Generator {
        $command = $this->arguments->command(
            binary: $this->binary,
            prompt: $prompt,
            session: $session,
            run: $run,
            resumeSessionId: $resumeSessionId,
        );
        $compatibility = new MacReadOnlySandbox;
        if ($resumeSessionId === null && $session->sandbox === SandboxProfile::ReadOnly && $compatibility->applicable($this->grokVersion, $this->env)) {
            $grokHome = $this->env['GROK_HOME'] ?? getenv('GROK_HOME');
            if (! is_string($grokHome) || $grokHome === '') {
                $userHome = $this->env['HOME'] ?? getenv('HOME');
                $grokHome = $userHome.'/.grok';
            }
            $position = array_search('--sandbox', $command, true);
            if ($position !== false) {
                $command[$position + 1] = $compatibility->install($grokHome);
            }
        }
        $process = $this->processes->create(
            $command,
            $session->cwd,
            $run?->timeout ?? $this->timeout,
            $this->env,
        );
        $builder = new ResultBuilder($resumeSessionId ?? '');
        $resultPayload = [];
        $model = null;

        $lines = $process->lines();
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }
            if (($decoded['type'] ?? null) === 'system' && ($decoded['subtype'] ?? null) === 'init') {
                $builder->setSessionId((string) ($decoded['session_id'] ?? ''));
                $model ??= self::modelName($decoded['model'] ?? null);
            }
            if ($model === null && ($decoded['type'] ?? null) === 'assistant') {
                $message = is_array($decoded['message'] ?? null) ? $decoded['message'] : [];
                $model ??= self::modelName($message['model'] ?? null);
            }
            if ($this->parser->isResult($decoded)) {
                $resultPayload = $decoded;
                $builder->setSessionId((string) ($decoded['session_id'] ?? ''));

                continue;
            }
            foreach ($this->parser->parse($decoded) as $update) {
                $builder->add($update);
                yield $update;
            }
        }

        /** @var ProcessResult $processResult */
        $processResult = $lines->getReturn();
        if ($resultPayload === [] && $processResult->exitCode !== 0) {
            throw new ProcessFailed(
                $processResult->exitCode,
                trim($processResult->stderr) !== '' ? $processResult->stderr : 'Grok Build headless run failed.',
            );
        }

        $metadata = new RunMetadata(
            grokVersion: $this->grokVersion,
            transport: 'headless',
            requestedModel: $session->model,
            cwd: $session->cwd,
        );

        if (isset($resultPayload['result']) && is_string($resultPayload['result']) && $resultPayload['result'] !== '') {
            $resultPayload['text'] = $resultPayload['result'];
        }

        return $builder->build($resultPayload, $metadata, UsageSource::Headless, $model);
    }

    public function run(
        Prompt $prompt,
        SessionOptions $session,
        ?RunOptions $run = null,
        ?string $resumeSessionId = null,
    ): Result {
        $stream = $this->stream($prompt, $session, $run, $resumeSessionId);
        foreach ($stream as $_) {
        }

        return $stream->getReturn();
    }

    private static function modelName(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
