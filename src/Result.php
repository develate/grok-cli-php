<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Tool\Command;
use Develate\GrokCli\Tool\FileChange;
use Develate\GrokCli\Tool\Plan;
use Develate\GrokCli\Update\AgentThoughtChunk;
use Develate\GrokCli\Update\PlanUpdate;
use Develate\GrokCli\Update\SessionUpdate;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Value\Cost;
use Develate\GrokCli\Value\ModelUsage;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\Usage;

final readonly class Result
{
    /**
     * @param  array<string, ModelUsage>  $modelUsage
     * @param  list<SessionUpdate>  $updates
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $sessionId,
        public string $text,
        public Usage $usage,
        public array $modelUsage,
        public ?Cost $cost,
        public int $numTurns,
        public ?string $stopReason,
        public array $updates,
        public mixed $structuredOutput,
        public RunMetadata $metadata,
        private array $raw = [],
    ) {
    }

    /** @return list<SessionUpdate> */
    public function updates(): array
    {
        return $this->updates;
    }

    /** @return list<ToolCall> */
    public function toolCalls(): array
    {
        $calls = [];
        foreach ($this->updates as $update) {
            if ($update instanceof ToolCall) {
                $calls[$update->id] = $update;
            }
        }

        return array_values($calls);
    }

    /** @return list<Command> */
    public function commands(): array
    {
        $commands = [];
        foreach ($this->toolCalls() as $call) {
            $command = $call->asCommand();
            if ($command !== null) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /** @return list<FileChange> */
    public function fileChanges(): array
    {
        $changes = [];
        foreach ($this->toolCalls() as $call) {
            $change = $call->asFileChange();
            if ($change !== null) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /** @return list<string> */
    public function thoughts(): array
    {
        $thoughts = [];
        foreach ($this->updates as $update) {
            if ($update instanceof AgentThoughtChunk && $update->text !== '') {
                $thoughts[] = $update->text;
            }
        }

        return $thoughts;
    }

    public function plan(): ?Plan
    {
        $plan = null;
        foreach ($this->updates as $update) {
            if ($update instanceof PlanUpdate) {
                $plan = $update->plan();
            }
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
