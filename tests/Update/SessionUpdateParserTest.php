<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Update;

use Develate\GrokCli\Tool\ToolCallStatus;
use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\AgentThoughtChunk;
use Develate\GrokCli\Update\PlanUpdate;
use Develate\GrokCli\Update\SessionUpdateParser;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Update\UnknownUpdate;
use PHPUnit\Framework\TestCase;

final class SessionUpdateParserTest extends TestCase
{
    public function test_it_parses_known_update_types(): void
    {
        $parser = new SessionUpdateParser;

        $message = $parser->parse([
            'method' => 'session/update',
            'params' => [
                'update' => [
                    'sessionUpdate' => 'agent_message_chunk',
                    'content' => ['type' => 'text', 'text' => 'Hello'],
                ],
            ],
        ]);
        $this->assertInstanceOf(AgentMessageChunk::class, $message);
        $this->assertSame('Hello', $message->text);

        $thought = $parser->parse([
            'params' => [
                'update' => [
                    'sessionUpdate' => 'agent_thought_chunk',
                    'content' => ['type' => 'text', 'text' => 'Hmm'],
                ],
            ],
        ]);
        $this->assertInstanceOf(AgentThoughtChunk::class, $thought);

        $tool = $parser->parse([
            'params' => [
                'update' => [
                    'sessionUpdate' => 'tool_call',
                    'toolCallId' => '1',
                    'title' => 'Read',
                    'kind' => 'read',
                    'status' => 'in_progress',
                    'toolName' => 'read_file',
                    'rawInput' => ['path' => 'a.php'],
                ],
            ],
        ]);
        $this->assertInstanceOf(ToolCall::class, $tool);
        $this->assertSame(ToolCallStatus::InProgress, $tool->status);
        $this->assertSame('read_file', $tool->name());

        $plan = $parser->parse([
            'params' => [
                'update' => [
                    'sessionUpdate' => 'plan',
                    'entries' => [['content' => 'Step one', 'status' => 'pending']],
                ],
            ],
        ]);
        $this->assertInstanceOf(PlanUpdate::class, $plan);
        $this->assertSame('Step one', $plan->entries[0]->content);
    }

    public function test_unknown_types_become_unknown_updates(): void
    {
        $parser = new SessionUpdateParser;
        $update = $parser->parse([
            'params' => ['update' => ['sessionUpdate' => 'something_new', 'foo' => 1]],
        ]);

        $this->assertInstanceOf(UnknownUpdate::class, $update);
        $this->assertSame('something_new', $update->type());
        $this->assertSame(1, $update->raw()['foo']);
    }
}
