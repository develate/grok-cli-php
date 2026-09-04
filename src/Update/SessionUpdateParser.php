<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

use Develate\GrokCli\Tool\PlanEntry;
use Develate\GrokCli\Tool\ToolCallStatus;

final class SessionUpdateParser
{
    /**
     * @param  array<string, mixed>  $message  a JSON-RPC notification or its params
     */
    public function parse(array $message): ?SessionUpdate
    {
        $params = $this->params($message);
        $update = is_array($params['update'] ?? null) ? $params['update'] : $params;
        $type = $update['sessionUpdate'] ?? $update['session_update'] ?? $params['sessionUpdate'] ?? null;

        if (! is_string($type) || $type === '') {
            $method = $message['method'] ?? $params['method'] ?? null;
            if (is_string($method) && $method !== '' && $method !== 'session/update') {
                return new UnknownUpdate($method, $message);
            }

            return null;
        }

        return match ($type) {
            'agent_message_chunk' => new AgentMessageChunk($this->contentText($update), $update),
            'agent_thought_chunk' => new AgentThoughtChunk($this->contentText($update), $update),
            'user_message_chunk' => new UserMessageChunk($this->contentText($update), $update),
            'tool_call' => $this->toolCall($update),
            'tool_call_update' => $this->toolCallUpdate($update),
            'plan' => $this->plan($update),
            default => new UnknownUpdate($type, $update),
        };
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function toolCall(array $update): ToolCall
    {
        $id = (string) ($update['toolCallId'] ?? $update['tool_call_id'] ?? $update['id'] ?? '');
        $input = $update['rawInput'] ?? $update['input'] ?? null;

        return new ToolCall(
            id: $id,
            title: (string) ($update['title'] ?? $update['toolName'] ?? ''),
            kind: isset($update['kind']) && is_string($update['kind']) ? $update['kind'] : null,
            input: $input,
            status: ToolCallStatus::fromValue($update['status'] ?? null),
            toolName: isset($update['toolName']) && is_string($update['toolName']) ? $update['toolName'] : null,
            output: $update['rawOutput'] ?? $update['output'] ?? null,
            raw: $update,
        );
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function toolCallUpdate(array $update): ToolCallUpdate
    {
        return new ToolCallUpdate(
            id: (string) ($update['toolCallId'] ?? $update['tool_call_id'] ?? $update['id'] ?? ''),
            status: array_key_exists('status', $update) ? ToolCallStatus::fromValue($update['status']) : null,
            output: $update['rawOutput'] ?? $update['output'] ?? null,
            raw: $update,
        );
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function plan(array $update): PlanUpdate
    {
        $entries = [];
        foreach ($update['entries'] ?? [] as $entry) {
            if (is_array($entry)) {
                $entries[] = PlanEntry::fromArray($entry);
            }
        }

        return new PlanUpdate($entries, $update);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function contentText(array $update): string
    {
        $content = $update['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }
        if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
            return $content['text'];
        }

        return is_string($update['text'] ?? null) ? $update['text'] : '';
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function params(array $message): array
    {
        if (isset($message['params']) && is_array($message['params'])) {
            return $message['params'];
        }

        return $message;
    }
}
