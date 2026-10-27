<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests;

use Develate\GrokCli\Grok;
use Develate\GrokCli\Permission\PermissionDecision;
use Develate\GrokCli\Permission\PermissionRequest;
use Develate\GrokCli\Tests\Support\FakeConnection;
use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Value\PermissionMode;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\SessionCapability;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_prompt_streams_updates_and_builds_a_result(): void
    {
        $connection = new FakeConnection;
        $grok = new Grok(connection: $connection);
        $session = $grok->session(cwd: '/tmp', model: 'grok-4.6', permissionMode: PermissionMode::BypassPermissions);

        $run = $session->stream('Reply with pong');
        $chunks = [];
        foreach ($run as $update) {
            $chunks[] = $update;
        }
        $result = $run->result();

        $this->assertNotEmpty($chunks);
        $this->assertInstanceOf(AgentMessageChunk::class, $chunks[0]);
        $this->assertSame('pong', $chunks[0]->text);
        $this->assertSame('pong', $result->text);
        $this->assertSame('session-one', $result->sessionId);
        $this->assertSame('session-one', $session->id());
        $this->assertSame(100, $result->usage->inputTokens);
        $this->assertSame(40, $result->usage->cacheReadInputTokens);
        $this->assertSame('acp', $result->usage->source->value);
        $this->assertSame('grok-4.6', $result->model);
        $this->assertNotNull($result->cost);

        $methods = array_column($connection->requests, 0);
        $this->assertContains('initialize', $methods);
        $this->assertContains('authenticate', $methods);
        $this->assertContains('session/new', $methods);
        $this->assertContains('session/prompt', $methods);

        $new = $this->paramsFor($connection, 'session/new');
        $this->assertSame('/tmp', $new['cwd']);
        $this->assertTrue($new['_meta']['yoloMode']);
    }

    public function test_unknown_updates_are_tolerated(): void
    {
        $connection = new FakeConnection;
        $connection->promptNotifications = [
            [
                'method' => 'session/update',
                'params' => [
                    'update' => ['sessionUpdate' => 'config_option_update', 'options' => []],
                ],
            ],
            [
                'method' => 'session/update',
                'params' => [
                    'update' => [
                        'sessionUpdate' => 'agent_message_chunk',
                        'content' => ['type' => 'text', 'text' => 'ok'],
                    ],
                ],
            ],
        ];
        $session = (new Grok(connection: $connection))->session(cwd: '/tmp');
        $result = $session->prompt('Hi');

        $this->assertSame('ok', $result->text);
        $this->assertSame('config_option_update', $result->updates()[0]->type());
    }

    public function test_tool_calls_are_first_class_and_commands_are_convenience(): void
    {
        $connection = new FakeConnection;
        $connection->promptNotifications = [
            [
                'method' => 'session/update',
                'params' => [
                    'update' => [
                        'sessionUpdate' => 'tool_call',
                        'toolCallId' => 'call_1',
                        'title' => 'Run',
                        'kind' => 'execute',
                        'status' => 'in_progress',
                        'toolName' => 'run_terminal_cmd',
                        'rawInput' => ['command' => 'ls'],
                    ],
                ],
            ],
            [
                'method' => 'session/update',
                'params' => [
                    'update' => [
                        'sessionUpdate' => 'tool_call_update',
                        'toolCallId' => 'call_1',
                        'status' => 'completed',
                        'rawOutput' => ['output' => 'a.txt', 'exit_code' => 0],
                    ],
                ],
            ],
            [
                'method' => 'session/update',
                'params' => [
                    'update' => [
                        'sessionUpdate' => 'agent_message_chunk',
                        'content' => ['type' => 'text', 'text' => 'listed'],
                    ],
                ],
            ],
        ];

        $result = (new Grok(connection: $connection))->session(cwd: '/tmp')->prompt('list');
        $this->assertCount(1, $result->toolCalls());
        $this->assertInstanceOf(ToolCall::class, $result->toolCalls()[0]);
        $this->assertTrue($result->toolCalls()[0]->isCommand());
        $this->assertSame('ls', $result->commands()[0]->command);
        $this->assertSame('a.txt', $result->commands()[0]->output);
    }

    public function test_resume_loads_the_session(): void
    {
        $connection = new FakeConnection;
        $session = (new Grok(connection: $connection))->resume('session-one', '/tmp');
        $session->prompt('continue');

        $this->assertSame('session-one', $session->id());
        $this->assertNotNull($this->paramsFor($connection, 'session/load'));
        $this->assertSame('session-one', $this->paramsFor($connection, 'session/load')['sessionId']);
    }

    public function test_fork_uses_the_xai_extension(): void
    {
        $connection = new FakeConnection;
        $session = (new Grok(connection: $connection))->session(cwd: '/tmp');
        $session->prompt('start');
        $branch = $session->fork();

        $this->assertSame('session-fork', $branch->id());
        $this->assertNotNull($this->paramsFor($connection, '_x.ai/session/fork'));
    }

    public function test_cancel_sends_a_session_notification(): void
    {
        $connection = new FakeConnection;
        $session = (new Grok(connection: $connection))->session(cwd: '/tmp');
        $run = $session->stream('long');
        $run->cancel();

        $this->assertSame([['session/cancel', ['sessionId' => 'session-one']]], $connection->notifications);
    }

    public function test_set_reasoning_effort_uses_config_option(): void
    {
        $connection = new FakeConnection;
        $session = (new Grok(connection: $connection))->session(cwd: '/tmp');
        $session->setReasoningEffort(ReasoningEffort::Max);

        $params = $this->paramsFor($connection, 'session/set_config_option');
        $this->assertSame('reasoning_effort', $params['configId']);
        $this->assertSame('max', $params['value']['value']);
    }

    public function test_capabilities_are_discovered(): void
    {
        $connection = new FakeConnection;
        $grok = new Grok(connection: $connection);

        $this->assertTrue($grok->supportsExtension('x.ai/billing'));
        $this->assertTrue($grok->session(cwd: '/tmp')->supports(SessionCapability::Close));
        $this->assertSame('grok-4.6', $grok->models()[0]->id);
        $this->assertSame('user@example.com', $grok->identity()?->email);
    }

    public function test_permission_callback_is_invoked(): void
    {
        $connection = new FakeConnection;
        $session = (new Grok(connection: $connection))->session(cwd: '/tmp');
        $seen = null;
        $session->onPermissionRequest(function (PermissionRequest $request) use (&$seen): PermissionDecision {
            $seen = $request->toolName();

            return PermissionDecision::allow($request->allowOnce());
        });

        $handler = $connection->requestHandler();
        $this->assertNotNull($handler);
        $response = $handler([
            'id' => 9,
            'method' => 'session/request_permission',
            'params' => [
                'sessionId' => 'session-one',
                'toolCall' => ['title' => 'run_terminal_cmd', 'rawInput' => ['command' => 'ls']],
                'options' => [
                    ['optionId' => 'allow-once', 'name' => 'Allow once', 'kind' => 'allow_once'],
                    ['optionId' => 'reject-once', 'name' => 'Reject', 'kind' => 'reject_once'],
                ],
            ],
        ]);

        $this->assertSame('run_terminal_cmd', $seen);
        $this->assertSame('allow-once', $response['outcome']['optionId']);
    }

    /** @return array<string, mixed> */
    private function paramsFor(FakeConnection $connection, string $method): array
    {
        foreach ($connection->requests as [$name, $params]) {
            if ($name === $method) {
                return $params ?? [];
            }
        }

        $this->fail('No request for '.$method);
    }
}
