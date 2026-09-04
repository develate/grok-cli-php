<?php

declare(strict_types=1);

namespace Develate\GrokCli\Acp;

use Develate\GrokCli\Exception\GrokNotFound;
use Develate\GrokCli\Exception\ProtocolException;
use Develate\GrokCli\Process\GrokExecutable;

final class StdioConnection implements Connection
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private int $nextId = 1;

    /** @var list<callable(array<string, mixed>): void> */
    private array $notificationListeners = [];

    /** @var (callable(array<string, mixed>): array<string, mixed>|null)|null */
    private $requestHandler = null;

    /**
     * @param  list<string>  $arguments  extra arguments inserted before `stdio`
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly string $binary = 'grok',
        private readonly array $arguments = [],
        private readonly array $env = [],
        private readonly float $timeout = 30.0,
        private readonly ?string $cwd = null,
    ) {
    }

    public function connect(): void
    {
        if (is_resource($this->process)) {
            return;
        }

        GrokExecutable::assertAvailable($this->binary);
        $command = [$this->binary, '--no-auto-update', 'agent', '--no-leader', ...$this->arguments, 'stdio'];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->cwd,
            $this->processEnvironment(),
            ['bypass_shell' => true],
        );
        if (! is_resource($process)) {
            throw new GrokNotFound(sprintf('Unable to start Grok Build binary "%s".', $this->binary));
        }

        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    public function request(string $method, ?array $params = null): mixed
    {
        $stream = $this->streamRequest($method, $params);
        foreach ($stream as $notification) {
            foreach ($this->notificationListeners as $listener) {
                $listener($notification);
            }
        }

        return $stream->getReturn();
    }

    public function streamRequest(string $method, ?array $params = null): \Generator
    {
        $this->connect();
        $id = $this->nextId++;
        $this->write(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params ?? new \stdClass]);

        while (true) {
            $message = $this->read();
            if (isset($message['method']) && array_key_exists('id', $message)) {
                $this->handleIncoming($message);
                continue;
            }
            if (isset($message['method']) && ! array_key_exists('id', $message)) {
                yield $message;
                continue;
            }
            if (($message['id'] ?? null) !== $id) {
                continue;
            }
            if (isset($message['error']) && is_array($message['error'])) {
                throw new ProtocolException(
                    is_string($message['error']['message'] ?? null) ? $message['error']['message'] : 'ACP request failed.',
                    is_numeric($message['error']['code'] ?? null) ? (int) $message['error']['code'] : null,
                    $message['error']['data'] ?? null,
                );
            }

            return $message['result'] ?? null;
        }
    }

    public function notify(string $method, ?array $params = null): void
    {
        $this->connect();
        $message = ['jsonrpc' => '2.0', 'method' => $method];
        if ($params !== null) {
            $message['params'] = $params;
        }
        $this->write($message);
    }

    public function respond(mixed $id, mixed $result): void
    {
        $this->connect();
        $this->write(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result ?? new \stdClass]);
    }

    public function onNotification(callable $listener): void
    {
        $this->notificationListeners[] = $listener;
    }

    public function onRequest(callable $handler): void
    {
        $this->requestHandler = $handler;
    }

    public function close(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running'] ?? false) {
                proc_terminate($this->process);
            }
            proc_close($this->process);
        }
        $this->process = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleIncoming(array $message): void
    {
        $handler = $this->requestHandler;
        $result = $handler !== null ? $handler($message) : ['outcome' => ['outcome' => 'cancelled']];
        if ($result === null) {
            $this->write([
                'jsonrpc' => '2.0',
                'id' => $message['id'],
                'error' => ['code' => -32601, 'message' => 'Method not found'],
            ]);

            return;
        }

        $this->respond($message['id'], $result);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function write(array $message): void
    {
        $encoded = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        $written = 0;
        while ($written < strlen($encoded)) {
            $bytes = fwrite($this->pipes[0], substr($encoded, $written));
            if ($bytes === false || $bytes === 0) {
                throw new ProtocolException('Unable to write to the Grok ACP agent.');
            }
            $written += $bytes;
        }
        fflush($this->pipes[0]);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $deadline = microtime(true) + $this->timeout;
        $buffer = '';

        while (microtime(true) < $deadline) {
            $read = [$this->pipes[1]];
            $write = null;
            $except = null;
            $remaining = max(0.0, $deadline - microtime(true));
            $seconds = (int) $remaining;
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);
            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                throw new ProtocolException('Failed while waiting for the Grok ACP agent.');
            }
            if ($ready === 0) {
                continue;
            }

            $chunk = fgets($this->pipes[1]);
            if ($chunk === false) {
                if (feof($this->pipes[1])) {
                    throw new ProtocolException('Grok ACP agent closed its output. '.$this->stderr());
                }
                continue;
            }
            $buffer .= $chunk;
            if (! str_ends_with($buffer, "\n")) {
                continue;
            }

            try {
                $message = json_decode(trim($buffer), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ProtocolException('Grok ACP agent emitted invalid JSON.', previous: $exception);
            }
            if (! is_array($message)) {
                throw new ProtocolException('Grok ACP agent emitted a non-object message.');
            }

            return $message;
        }

        throw new ProtocolException(sprintf('Grok ACP agent did not respond within %.1f seconds. %s', $this->timeout, $this->stderr()));
    }

    private function stderr(): string
    {
        return isset($this->pipes[2]) && is_resource($this->pipes[2]) ? trim((string) stream_get_contents($this->pipes[2])) : '';
    }

    /** @return array<string, string>|null */
    private function processEnvironment(): ?array
    {
        if ($this->env === []) {
            return null;
        }

        $inherited = getenv();
        $env = is_array($inherited) ? $inherited : [];
        foreach ($this->env as $key => $value) {
            if ($value === false) {
                unset($env[$key]);
                continue;
            }
            $env[$key] = $value;
        }

        $env['GROK_DISABLE_AUTOUPDATER'] = $env['GROK_DISABLE_AUTOUPDATER'] ?? '1';

        return $env;
    }
}
