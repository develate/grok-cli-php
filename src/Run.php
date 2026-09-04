<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Acp\Connection;
use Develate\GrokCli\Exception\ProcessCancelled;
use Develate\GrokCli\Update\SessionUpdate;
use Develate\GrokCli\Update\SessionUpdateParser;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\UsageSource;

/** @implements \IteratorAggregate<int, SessionUpdate> */
final class Run implements \IteratorAggregate
{
    private bool $cancelled = false;

    private bool $iterating = false;

    private bool $completed = false;

    private ?Result $lastResult = null;

    private ?\Generator $execution = null;

    private ?\Throwable $failure = null;

    /** @var list<SessionUpdate> */
    private array $updates = [];

    /**
     * @param  array<string, mixed>  $params
     * @param  callable(Result): void  $onComplete
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly array $params,
        private readonly string $sessionId,
        private readonly RunMetadata $metadata,
        private readonly SessionUpdateParser $parser,
        private readonly \Closure $onComplete,
        private readonly \Closure $onCancel,
    ) {
    }

    public function getIterator(): \Traversable
    {
        if ($this->completed) {
            yield from $this->updates;

            return;
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->iterating) {
            throw new \LogicException('A run cannot be iterated concurrently.');
        }

        $this->execution ??= $this->execute();
        $this->iterating = true;
        try {
            while ($this->execution->valid()) {
                yield $this->execution->current();
                $this->execution->next();
            }
        } finally {
            $this->iterating = false;
        }
    }

    public function result(): Result
    {
        if (! $this->completed) {
            foreach ($this as $_) {
            }
        }

        return $this->lastResult ?? throw $this->failure ?? new \LogicException('The run did not produce a result.');
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        ($this->onCancel)();
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    /** @return list<SessionUpdate> */
    public function updates(): array
    {
        return $this->updates;
    }

    /** @return \Generator<int, SessionUpdate, void, void> */
    private function execute(): \Generator
    {
        $builder = new ResultBuilder($this->sessionId);
        try {
            $stream = $this->connection->streamRequest('session/prompt', $this->params);
            foreach ($stream as $notification) {
                if ($this->cancelled) {
                    throw new ProcessCancelled('Grok Build run was cancelled.');
                }
                $update = $this->parser->parse($notification);
                if ($update === null) {
                    continue;
                }
                $builder->add($update);
                $this->updates[] = $update;
                yield $update;
            }

            $promptResult = $stream->getReturn();
            $promptResult = is_array($promptResult) ? $promptResult : [];
            $this->lastResult = $builder->build($promptResult, $this->metadata, UsageSource::Acp);
            ($this->onComplete)($this->lastResult);
            $this->completed = true;
        } catch (\Throwable $exception) {
            $this->failure = $exception;
            throw $exception;
        }
    }
}
