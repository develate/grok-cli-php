<?php

declare(strict_types=1);

namespace Develate\GrokCli\Acp;

interface Connection
{
    /**
     * @param  array<string, mixed>|null  $params
     */
    public function request(string $method, ?array $params = null): mixed;

    /**
     * Sends a request and yields JSON-RPC notifications until the matching response arrives.
     *
     * Incoming JSON-RPC requests from the agent are handled internally via {@see self::onRequest()}.
     *
     * @param  array<string, mixed>|null  $params
     * @return \Generator<int, array<string, mixed>, void, mixed>
     */
    public function streamRequest(string $method, ?array $params = null): \Generator;

    /**
     * @param  array<string, mixed>|null  $params
     */
    public function notify(string $method, ?array $params = null): void;

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function respond(mixed $id, mixed $result): void;

    /**
     * @param  callable(array<string, mixed>): void  $listener
     */
    public function onNotification(callable $listener): void;

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $handler
     */
    public function onRequest(callable $handler): void;

    public function close(): void;
}
