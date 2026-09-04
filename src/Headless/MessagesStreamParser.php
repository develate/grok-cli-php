<?php

declare(strict_types=1);

namespace Develate\GrokCli\Headless;

use Develate\GrokCli\Tool\ToolCallStatus;
use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\AgentThoughtChunk;
use Develate\GrokCli\Update\SessionUpdate;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Update\ToolCallUpdate;
use Develate\GrokCli\Update\UnknownUpdate;

final class MessagesStreamParser
{
    /**
     * @return list<SessionUpdate>
     */
    public function parseLine(string $line): array
    {
        $decoded = json_decode($line, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $this->parse($decoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<SessionUpdate>
     */
    public function parse(array $payload): array
    {
        $type = $payload['type'] ?? null;
        if (! is_string($type)) {
            return [new UnknownUpdate('unknown', $payload)];
        }

        return match ($type) {
            'assistant' => $this->assistant($payload),
            'user' => $this->user($payload),
            'system', 'result' => [],
            'stream_event' => $this->streamEvent($payload),
            default => [new UnknownUpdate($type, $payload)],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isResult(array $payload): bool
    {
        return ($payload['type'] ?? null) === 'result';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<SessionUpdate>
     */
    private function assistant(array $payload): array
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];
        $updates = [];
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? '';
            if ($type === 'text' && is_string($block['text'] ?? null) && $block['text'] !== '') {
                $updates[] = new AgentMessageChunk($block['text'], $block);
            }
            if ($type === 'thinking' && is_string($block['thinking'] ?? $block['text'] ?? null)) {
                $updates[] = new AgentThoughtChunk((string) ($block['thinking'] ?? $block['text']), $block);
            }
            if ($type === 'tool_use') {
                $updates[] = new ToolCall(
                    id: (string) ($block['id'] ?? ''),
                    title: (string) ($block['name'] ?? ''),
                    kind: null,
                    input: $block['input'] ?? null,
                    status: ToolCallStatus::InProgress,
                    toolName: isset($block['name']) && is_string($block['name']) ? $block['name'] : null,
                    raw: $block,
                );
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<SessionUpdate>
     */
    private function user(array $payload): array
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];
        $updates = [];
        foreach ($content as $block) {
            if (! is_array($block) || ($block['type'] ?? '') !== 'tool_result') {
                continue;
            }
            $updates[] = new ToolCallUpdate(
                id: (string) ($block['tool_use_id'] ?? $block['toolUseId'] ?? ''),
                status: ($block['is_error'] ?? false) ? ToolCallStatus::Failed : ToolCallStatus::Completed,
                output: $block['content'] ?? null,
                raw: $block,
            );
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<SessionUpdate>
     */
    private function streamEvent(array $payload): array
    {
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : $payload;
        $type = $event['type'] ?? '';
        if ($type === 'content_block_delta') {
            $delta = is_array($event['delta'] ?? null) ? $event['delta'] : [];
            if (($delta['type'] ?? '') === 'text_delta' && is_string($delta['text'] ?? null)) {
                return [new AgentMessageChunk($delta['text'], $payload)];
            }
            if (($delta['type'] ?? '') === 'thinking_delta' && is_string($delta['thinking'] ?? $delta['text'] ?? null)) {
                return [new AgentThoughtChunk((string) ($delta['thinking'] ?? $delta['text']), $payload)];
            }
        }

        return [];
    }
}
