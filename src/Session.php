<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Acp\CapabilitySet;
use Develate\GrokCli\Acp\Connection;
use Develate\GrokCli\Exception\ProtocolException;
use Develate\GrokCli\Exception\UnsupportedCapability;
use Develate\GrokCli\Permission\PermissionDecision;
use Develate\GrokCli\Permission\PermissionRequest;
use Develate\GrokCli\Update\SessionUpdateParser;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\SessionCapability;

final class Session
{
    private ?Result $lastResult = null;

    private ?string $sessionId;

    /** @var (callable(PermissionRequest): PermissionDecision)|null */
    private $permissionHandler = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly CapabilitySet $capabilities,
        private SessionOptions $options,
        private readonly RunMetadata $metadata,
        private readonly SessionUpdateParser $parser = new SessionUpdateParser,
        ?string $sessionId = null,
        private readonly bool $ownsConnection = true,
    ) {
        $this->sessionId = $sessionId;
        $this->connection->onRequest($this->handleIncoming(...));
    }

    public function id(): ?string
    {
        return $this->sessionId;
    }

    public function options(): SessionOptions
    {
        return $this->options;
    }

    public function capabilities(): CapabilitySet
    {
        return $this->capabilities;
    }

    public function supports(SessionCapability $capability): bool
    {
        return $this->capabilities->supports($capability);
    }

    /**
     * @param  callable(PermissionRequest): PermissionDecision  $handler
     */
    public function onPermissionRequest(callable $handler): self
    {
        $this->permissionHandler = $handler;

        return $this;
    }

    public function prompt(string|Prompt $prompt, ?RunOptions $options = null): Result
    {
        return $this->stream($prompt, $options)->result();
    }

    public function stream(string|Prompt $prompt, ?RunOptions $options = null): Run
    {
        $this->ensureSession();
        $prompt = is_string($prompt)
            ? Prompt::from($prompt, $options->images ?? [])
            : $prompt;

        $imagesAsResources = ! $this->capabilities->supportsImagePrompts() && $this->capabilities->supportsEmbeddedContext();
        $params = [
            'sessionId' => $this->sessionId,
            'prompt' => $prompt->toContent($imagesAsResources),
        ];

        return new Run(
            connection: $this->connection,
            params: $params,
            sessionId: (string) $this->sessionId,
            metadata: $this->metadata,
            parser: $this->parser,
            onComplete: function (Result $result): void {
                $this->lastResult = $result;
                if ($result->sessionId !== '') {
                    $this->sessionId = $result->sessionId;
                }
            },
            onCancel: function (): void {
                if ($this->sessionId !== null) {
                    $this->connection->notify('session/cancel', ['sessionId' => $this->sessionId]);
                }
            },
        );
    }

    public function result(): Result
    {
        return $this->lastResult ?? throw new \LogicException('This session has no completed result yet.');
    }

    public function cancel(): void
    {
        if ($this->sessionId !== null) {
            $this->connection->notify('session/cancel', ['sessionId' => $this->sessionId]);
        }
    }

    public function close(): void
    {
        if ($this->sessionId !== null && $this->supports(SessionCapability::Close)) {
            try {
                $this->connection->request('session/close', ['sessionId' => $this->sessionId]);
            } catch (ProtocolException) {
                // Older agents advertise close without implementing it.
            }
        }
        if ($this->ownsConnection) {
            $this->connection->close();
        }
    }

    public function setModel(string $model): self
    {
        $this->setConfigOption('model', $model);
        $this->options = new SessionOptions(
            cwd: $this->options->cwd,
            model: $model,
            reasoningEffort: $this->options->reasoningEffort,
            permissionMode: $this->options->permissionMode,
            sandbox: $this->options->sandbox,
            allow: $this->options->allow,
            deny: $this->options->deny,
            mcpServers: $this->options->mcpServers,
            rules: $this->options->rules,
            systemPromptOverride: $this->options->systemPromptOverride,
            tools: $this->options->tools,
            disallowedTools: $this->options->disallowedTools,
            plan: $this->options->plan,
        );

        return $this;
    }

    public function setReasoningEffort(ReasoningEffort $effort): self
    {
        $this->setConfigOption('reasoning_effort', $effort->value);
        $this->options = new SessionOptions(
            cwd: $this->options->cwd,
            model: $this->options->model,
            reasoningEffort: $effort,
            permissionMode: $this->options->permissionMode,
            sandbox: $this->options->sandbox,
            allow: $this->options->allow,
            deny: $this->options->deny,
            mcpServers: $this->options->mcpServers,
            rules: $this->options->rules,
            systemPromptOverride: $this->options->systemPromptOverride,
            tools: $this->options->tools,
            disallowedTools: $this->options->disallowedTools,
            plan: $this->options->plan,
        );

        return $this;
    }

    public function fork(): self
    {
        $this->ensureSession();
        try {
            $result = $this->connection->request('_x.ai/session/fork', ['sessionId' => $this->sessionId]);
        } catch (ProtocolException $exception) {
            throw new UnsupportedCapability('This Grok Build agent does not support session forking.', 0, $exception);
        }

        $payload = is_array($result) ? $result : [];
        $id = (string) ($payload['sessionId'] ?? '');
        if ($id === '') {
            throw new ProtocolException('Grok Build did not return a forked session id.');
        }

        return new self(
            connection: $this->connection,
            capabilities: $this->capabilities,
            options: $this->options,
            metadata: $this->metadata,
            parser: $this->parser,
            sessionId: $id,
            ownsConnection: false,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function extension(string $method, array $params = []): mixed
    {
        $rpc = str_starts_with($method, '_') ? $method : '_'.$method;
        if (! isset($params['sessionId']) && $this->sessionId !== null) {
            $params = ['sessionId' => $this->sessionId] + $params;
        }

        return $this->connection->request($rpc, $params);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function handleIncoming(array $message): ?array
    {
        $method = $message['method'] ?? '';
        if ($method !== 'session/request_permission') {
            return null;
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $request = PermissionRequest::fromParams($message['id'] ?? null, $params);
        $handler = $this->permissionHandler;
        if ($handler === null) {
            $option = $this->options->permissionMode?->isAlwaysApprove()
                ? $request->allowOnce()
                : $request->rejectOnce();

            return $option !== null
                ? PermissionDecision::selected($option->optionId)->payload
                : PermissionDecision::cancelled()->payload;
        }

        return $handler($request)->payload;
    }

    private function ensureSession(): void
    {
        if ($this->sessionId !== null && $this->sessionId !== '') {
            return;
        }

        $params = [
            'cwd' => $this->options->cwd,
            'mcpServers' => $this->options->mcpServers,
        ];
        $meta = $this->options->sessionMeta();
        if ($meta !== []) {
            $params['_meta'] = $meta;
        }

        $result = $this->connection->request('session/new', $params);
        $payload = is_array($result) ? $result : [];
        $this->sessionId = (string) ($payload['sessionId'] ?? '');
        if ($this->sessionId === '') {
            throw new ProtocolException('Grok Build did not return a session id.');
        }
    }

    private function setConfigOption(string $configId, string $value): void
    {
        $this->ensureSession();
        $this->connection->request('session/set_config_option', [
            'sessionId' => $this->sessionId,
            'configId' => $configId,
            'value' => ['value' => $value],
        ]);
    }
}
