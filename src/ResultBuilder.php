<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\SessionUpdate;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Update\ToolCallUpdate;
use Develate\GrokCli\Value\Cost;
use Develate\GrokCli\Value\ModelUsage;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\Usage;
use Develate\GrokCli\Value\UsageSource;

final class ResultBuilder
{
    /** @var list<SessionUpdate> */
    private array $updates = [];

    /** @var array<string, ToolCall> */
    private array $toolCalls = [];

    private string $text = '';

    public function __construct(
        private string $sessionId = '',
    ) {
    }

    public function add(SessionUpdate $update): void
    {
        if ($update instanceof ToolCall) {
            $existing = $this->toolCalls[$update->id] ?? null;
            $update = $existing !== null ? $existing->withUpdate($update->raw()) : $update;
            $this->toolCalls[$update->id] = $update;
        } elseif ($update instanceof ToolCallUpdate) {
            $existing = $this->toolCalls[$update->id] ?? null;
            if ($existing !== null) {
                $merged = $existing->withUpdate($update->raw());
                $this->toolCalls[$update->id] = $merged;
                $update = $merged;
            }
        } elseif ($update instanceof AgentMessageChunk) {
            $this->text .= $update->text;
        }

        $this->updates[] = $update;
    }

    public function setSessionId(string $sessionId): void
    {
        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }
    }

    /**
     * @param  array<string, mixed>  $promptResult
     * @param  string|null  $effectiveModel  the model the run executed on, when
     *                                       the prompt result itself does not name it
     */
    public function build(
        array $promptResult,
        RunMetadata $metadata,
        UsageSource $source = UsageSource::Acp,
        ?string $effectiveModel = null,
    ): Result {
        $meta = is_array($promptResult['_meta'] ?? null) ? $promptResult['_meta'] : [];
        $modelId = $meta['modelId'] ?? $meta['model_id'] ?? null;
        $model = self::modelName($modelId) ?? self::modelName($effectiveModel);
        $usagePayload = is_array($meta['usage'] ?? null) ? $meta['usage'] : (is_array($promptResult['usage'] ?? null) ? $promptResult['usage'] : $meta);
        $incomplete = (bool) ($promptResult['usage_is_incomplete'] ?? $meta['usage_is_incomplete'] ?? false);
        $usage = Usage::fromArray($usagePayload, $source, $incomplete);
        $modelUsage = ModelUsage::map(
            is_array($usagePayload['modelUsage'] ?? null) ? $usagePayload['modelUsage'] : ($promptResult['modelUsage'] ?? null),
        );
        $sessionId = (string) ($promptResult['sessionId'] ?? $meta['sessionId'] ?? $this->sessionId);
        $text = $this->text;
        if ($text === '' && isset($promptResult['text']) && is_string($promptResult['text'])) {
            $text = $promptResult['text'];
        }
        if ($text === '' && isset($promptResult['result']) && is_string($promptResult['result'])) {
            $text = $promptResult['result'];
        }

        $cost = Cost::fromArray($usagePayload + $promptResult + $meta, $usage->incomplete);
        $numTurns = $usagePayload['numTurns'] ?? $promptResult['num_turns'] ?? $meta['numTurns'] ?? 0;

        return new Result(
            sessionId: $sessionId,
            text: $text,
            usage: $usage,
            modelUsage: $modelUsage,
            cost: $cost,
            numTurns: is_numeric($numTurns) ? (int) $numTurns : 0,
            stopReason: isset($promptResult['stopReason']) && is_string($promptResult['stopReason'])
                ? $promptResult['stopReason']
                : (isset($promptResult['stop_reason']) && is_string($promptResult['stop_reason']) ? $promptResult['stop_reason'] : null),
            updates: $this->updates,
            structuredOutput: $promptResult['structured_output'] ?? $promptResult['structuredOutput'] ?? null,
            metadata: new RunMetadata(
                grokVersion: $metadata->grokVersion,
                transport: $metadata->transport,
                requestedModel: $metadata->requestedModel,
                cwd: $metadata->cwd,
                requestId: isset($meta['requestId']) && is_string($meta['requestId']) ? $meta['requestId'] : $metadata->requestId,
                protocolVersion: $metadata->protocolVersion,
                capabilities: $metadata->capabilities,
            ),
            raw: $promptResult,
            model: $model,
        );
    }

    private static function modelName(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
