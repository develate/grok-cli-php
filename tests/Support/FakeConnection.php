<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Support;

use Develate\GrokCli\Acp\Connection;

final class FakeConnection implements Connection
{
    /** @var list<array{0: string, 1: array<string, mixed>|null}> */
    public array $requests = [];

    /** @var list<array{0: string, 1: array<string, mixed>|null}> */
    public array $notifications = [];

    /** @var array<string, mixed> */
    public array $initializeResult;

    /** @var array<string, mixed> */
    public array $authenticateResult = ['_meta' => ['email' => 'user@example.com', 'auth_mode' => 'Oidc', 'subscription_tier' => 'SuperGrok']];

    /** @var array<string, mixed> */
    public array $sessionNewResult = ['sessionId' => 'session-one'];

    /** @var array<string, mixed> */
    public array $sessionLoadResult = ['sessionId' => 'session-one'];

    /** @var array<string, mixed> */
    public array $promptResult = [
        'stopReason' => 'end_turn',
        '_meta' => [
            'sessionId' => 'session-one',
            'requestId' => 'req-1',
            'modelId' => 'grok-4.6',
            'usage' => [
                'inputTokens' => 100,
                'outputTokens' => 20,
                'cachedReadTokens' => 40,
                'cacheCreationTokens' => 0,
                'reasoningTokens' => 5,
                'totalTokens' => 120,
                'numTurns' => 1,
                'costUsdTicks' => 1_000_000,
            ],
        ],
    ];

    /** @var list<array<string, mixed>> */
    public array $promptNotifications = [];

    /** @var array<string, mixed> */
    public array $billingResult = [
        'config' => [
            'creditUsagePercent' => 42.5,
            'currentPeriod' => [
                'type' => 'USAGE_PERIOD_TYPE_WEEKLY',
                'start' => '2026-09-01T00:00:00Z',
                'end' => '2026-09-08T00:00:00Z',
            ],
            'prepaidBalance' => ['val' => 1250],
            'onDemandCap' => ['val' => 500],
            'onDemandUsed' => ['val' => 50],
            'isUnifiedBillingUser' => true,
        ],
        'onDemandEnabled' => true,
        'subscription_tier' => 'SuperGrok',
    ];

    /** @var array<string, mixed> */
    public array $forkResult = ['sessionId' => 'session-fork'];

    public bool $closed = false;

    /** @var (callable(array<string, mixed>): array<string, mixed>|null)|null */
    private $requestHandler = null;

    public function __construct()
    {
        $this->initializeResult = [
            'protocolVersion' => 1,
            'agentCapabilities' => [
                'loadSession' => true,
                'promptCapabilities' => ['image' => false, 'embeddedContext' => true],
                'sessionCapabilities' => ['list' => new \stdClass, 'resume' => new \stdClass, 'close' => new \stdClass],
                '_meta' => ['x.ai/fs_notify' => true],
            ],
            'authMethods' => [
                ['id' => 'cached_token', 'name' => 'cached_token'],
            ],
            '_meta' => [
                'defaultAuthMethodId' => 'cached_token',
                'agentVersion' => '1.0.13',
                'modelState' => [
                    'currentModelId' => 'grok-4.6',
                    'availableModels' => [
                        [
                            'modelId' => 'grok-4.6',
                            'name' => 'Grok 4.6',
                            'description' => 'Frontier model',
                            '_meta' => [
                                'totalContextTokens' => 500000,
                                'supportsReasoningEffort' => true,
                                'reasoningEffort' => 'high',
                                'reasoningEfforts' => [
                                    ['id' => 'high', 'value' => 'high'],
                                    ['id' => 'low', 'value' => 'low'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->promptNotifications = [
            [
                'jsonrpc' => '2.0',
                'method' => 'session/update',
                'params' => [
                    'sessionId' => 'session-one',
                    'update' => [
                        'sessionUpdate' => 'agent_message_chunk',
                        'content' => ['type' => 'text', 'text' => 'pong'],
                    ],
                ],
            ],
        ];
    }

    public function request(string $method, ?array $params = null): mixed
    {
        $stream = $this->streamRequest($method, $params);
        foreach ($stream as $_) {
        }

        return $stream->getReturn();
    }

    public function streamRequest(string $method, ?array $params = null): \Generator
    {
        $this->requests[] = [$method, $params];

        if ($method === 'session/prompt') {
            foreach ($this->promptNotifications as $notification) {
                yield $notification;
            }

            return $this->promptResult;
        }

        return match ($method) {
            'initialize' => $this->initializeResult,
            'authenticate' => $this->authenticateResult,
            'session/new' => $this->sessionNewResult,
            'session/load' => $this->sessionLoadResult,
            'session/close' => [],
            'session/set_config_option' => [],
            '_x.ai/billing' => $this->billingResult,
            '_x.ai/session/fork' => $this->forkResult,
            default => [],
        };
    }

    public function notify(string $method, ?array $params = null): void
    {
        $this->notifications[] = [$method, $params];
    }

    public function respond(mixed $id, mixed $result): void
    {
    }

    public function onNotification(callable $listener): void
    {
    }

    public function onRequest(callable $handler): void
    {
        $this->requestHandler = $handler;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function requestHandler(): ?callable
    {
        return $this->requestHandler;
    }
}
