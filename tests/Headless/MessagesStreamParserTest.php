<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Headless;

use Develate\GrokCli\Headless\MessagesStreamParser;
use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\ToolCall;
use PHPUnit\Framework\TestCase;

final class MessagesStreamParserTest extends TestCase
{
    public function test_it_maps_assistant_blocks(): void
    {
        $parser = new MessagesStreamParser;
        $updates = $parser->parse([
            'type' => 'assistant',
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'Hello'],
                    ['type' => 'tool_use', 'id' => '1', 'name' => 'read_file', 'input' => ['path' => 'a.php']],
                ],
            ],
        ]);

        $this->assertInstanceOf(AgentMessageChunk::class, $updates[0]);
        $this->assertSame('Hello', $updates[0]->text);
        $this->assertInstanceOf(ToolCall::class, $updates[1]);
        $this->assertSame('read_file', $updates[1]->name());
    }

    public function test_unknown_message_types_are_kept(): void
    {
        $parser = new MessagesStreamParser;
        $updates = $parser->parse(['type' => 'max_turns_reached']);

        $this->assertSame('max_turns_reached', $updates[0]->type());
    }
}
