<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Acp\CapabilitySet;
use Develate\GrokCli\Acp\Connection;
use Develate\GrokCli\Acp\StdioConnection;
use Develate\GrokCli\Billing\Billing;
use Develate\GrokCli\Exception\BillingUnavailable;
use Develate\GrokCli\Exception\GrokNotFound;
use Develate\GrokCli\Exception\ProcessFailed;
use Develate\GrokCli\Exception\ProtocolException;
use Develate\GrokCli\Headless\HeadlessClient;
use Develate\GrokCli\Process\GrokExecutable;
use Develate\GrokCli\Process\ProcessFactory;
use Develate\GrokCli\Update\SessionUpdateParser;
use Develate\GrokCli\Value\Identity;
use Develate\GrokCli\Value\PermissionMode;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\SandboxProfile;
use Develate\GrokCli\Value\SessionCapability;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailed;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

final class Grok
{
    private ?string $versionCache;

    private ?CapabilitySet $capabilities = null;

    private ?Identity $identity = null;

    /**
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly string $binary = 'grok',
        private readonly array $env = [],
        private readonly ?float $timeout = null,
        ?string $version = null,
        private readonly ?Connection $connection = null,
        private readonly ProcessFactory $processes = new ProcessFactory,
        private readonly SessionUpdateParser $parser = new SessionUpdateParser,
    ) {
        $this->versionCache = $version;
    }

    public function session(
        string $cwd,
        ?string $model = null,
        ?ReasoningEffort $reasoningEffort = null,
        ?PermissionMode $permissionMode = null,
        ?SandboxProfile $sandbox = null,
        array $allow = [],
        array $deny = [],
        array $mcpServers = [],
        ?string $rules = null,
        ?string $systemPromptOverride = null,
        ?array $tools = null,
        ?array $disallowedTools = null,
        bool $plan = false,
    ): Session {
        $options = new SessionOptions(
            cwd: $cwd,
            model: $model,
            reasoningEffort: $reasoningEffort,
            permissionMode: $permissionMode,
            sandbox: $sandbox,
            allow: $allow,
            deny: $deny,
            mcpServers: $mcpServers,
            rules: $rules,
            systemPromptOverride: $systemPromptOverride,
            tools: $tools,
            disallowedTools: $disallowedTools,
            plan: $plan,
        );

        return $this->open($options);
    }

    public function open(SessionOptions $options): Session
    {
        $connection = $this->startConnection($options);
        $capabilities = $this->handshake($connection);

        return new Session(
            connection: $connection,
            capabilities: $capabilities,
            options: $options,
            metadata: new RunMetadata(
                grokVersion: $capabilities->agentVersion ?? $this->version(),
                transport: 'acp',
                requestedModel: $options->model,
                cwd: $options->cwd,
                protocolVersion: $capabilities->protocolVersion,
                capabilities: $capabilities->agent,
            ),
            parser: $this->parser,
        );
    }

    public function resume(string $sessionId, string $cwd, ?SessionOptions $options = null): Session
    {
        if ($sessionId === '') {
            throw new \InvalidArgumentException('sessionId must not be empty.');
        }

        $options ??= new SessionOptions(cwd: $cwd);
        $connection = $this->startConnection($options);
        $capabilities = $this->handshake($connection);
        if (! $capabilities->supports(SessionCapability::Load) && ! $capabilities->supports(SessionCapability::Resume)) {
            throw new ProtocolException('This Grok Build agent does not support session/load.');
        }

        $params = [
            'sessionId' => $sessionId,
            'cwd' => $options->cwd,
            'mcpServers' => $options->mcpServers,
        ];
        $meta = $options->sessionMeta();
        if ($meta !== []) {
            $params['_meta'] = $meta;
        }
        $connection->request('session/load', $params);

        return new Session(
            connection: $connection,
            capabilities: $capabilities,
            options: $options,
            metadata: new RunMetadata(
                grokVersion: $capabilities->agentVersion ?? $this->version(),
                transport: 'acp',
                requestedModel: $options->model,
                cwd: $options->cwd,
                protocolVersion: $capabilities->protocolVersion,
                capabilities: $capabilities->agent,
            ),
            parser: $this->parser,
            sessionId: $sessionId,
        );
    }

    public function run(
        string|Prompt $prompt,
        string $cwd,
        ?string $model = null,
        ?ReasoningEffort $reasoningEffort = null,
        ?PermissionMode $permissionMode = null,
        ?SandboxProfile $sandbox = null,
        ?int $maxTurns = null,
        ?array $schema = null,
        array $images = [],
        ?array $tools = null,
        ?array $disallowedTools = null,
        array $allow = [],
        array $deny = [],
        ?string $resumeSessionId = null,
        ?float $timeout = null,
    ): Result {
        $options = new SessionOptions(
            cwd: $cwd,
            model: $model,
            reasoningEffort: $reasoningEffort,
            permissionMode: $permissionMode ?? PermissionMode::BypassPermissions,
            sandbox: $sandbox,
            allow: $allow,
            deny: $deny,
            tools: $tools,
            disallowedTools: $disallowedTools,
        );
        $prompt = is_string($prompt) ? Prompt::from($prompt, $images) : $prompt;

        return $this->headless()->run(
            $prompt,
            $options,
            new RunOptions(images: $images, maxTurns: $maxTurns, schema: $schema, timeout: $timeout ?? $this->timeout),
            $resumeSessionId,
        );
    }

    public function billing(): Billing
    {
        $connection = $this->metadataConnection();
        $this->handshake($connection);
        try {
            $result = $connection->request('_x.ai/billing', []);
        } catch (ProtocolException $exception) {
            $connection->close();
            throw new BillingUnavailable('Grok Build billing is not available on this agent.', 0, $exception);
        }
        $connection->close();

        if (! is_array($result)) {
            throw new BillingUnavailable('Grok Build billing returned an empty response.');
        }

        return Billing::fromArray($result);
    }

    public function supportsBilling(): bool
    {
        try {
            $this->billing();

            return true;
        } catch (BillingUnavailable) {
            return false;
        }
    }

    public function capabilities(): CapabilitySet
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $connection = $this->metadataConnection();
        $capabilities = $this->handshake($connection);
        if ($this->connection === null) {
            $connection->close();
        }

        return $capabilities;
    }

    public function supportsExtension(string $name): bool
    {
        return $this->capabilities()->supportsExtension($name);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function extension(string $method, array $params = []): mixed
    {
        $rpc = str_starts_with($method, '_') ? $method : '_'.$method;
        $connection = $this->metadataConnection();
        $this->handshake($connection);
        try {
            return $connection->request($rpc, $params);
        } finally {
            if ($this->connection === null) {
                $connection->close();
            }
        }
    }

    public function identity(): ?Identity
    {
        $this->capabilities();

        return $this->identity;
    }

    public function version(): string
    {
        if ($this->versionCache !== null) {
            return $this->versionCache;
        }

        $output = trim($this->runCommand(['--version'], 5.0));
        if (preg_match('/([0-9]+(?:\.[0-9]+){1,3}(?:[-+][^\s]+)?)/', $output, $matches) === 1) {
            return $this->versionCache = $matches[1];
        }

        return $this->versionCache = $output;
    }

    /**
     * @return list<\Develate\GrokCli\Value\ModelInfo>
     */
    public function models(): array
    {
        return $this->capabilities()->models;
    }

    private function headless(): HeadlessClient
    {
        return new HeadlessClient(
            binary: $this->binary,
            env: $this->env,
            timeout: $this->timeout,
            processes: $this->processes,
            grokVersion: $this->versionCache ?? '',
        );
    }

    private function startConnection(SessionOptions $options): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $env = $this->env;
        if ($options->sandbox !== null) {
            $env['GROK_SANDBOX'] = $options->sandbox->value;
        }

        return new StdioConnection(
            binary: $this->binary,
            arguments: $options->agentArguments(),
            env: $env,
            timeout: $this->timeout ?? 120.0,
            cwd: $options->cwd,
        );
    }

    private function metadataConnection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        return new StdioConnection(
            binary: $this->binary,
            env: $this->env,
            timeout: $this->timeout ?? 30.0,
        );
    }

    private function handshake(Connection $connection): CapabilitySet
    {
        $result = $connection->request('initialize', [
            'protocolVersion' => 1,
            'clientInfo' => [
                'name' => 'develate/grok-cli-php',
                'version' => $this->versionCache ?? '1.0.0',
            ],
            'clientCapabilities' => [
                'fs' => ['readTextFile' => false, 'writeTextFile' => false],
            ],
        ]);
        $payload = is_array($result) ? $result : [];
        $capabilities = CapabilitySet::fromInitialize($payload);
        $this->capabilities = $capabilities;
        if ($capabilities->agentVersion !== null) {
            $this->versionCache ??= $capabilities->agentVersion;
        }

        $methodId = $capabilities->defaultAuthMethodId;
        if ($methodId !== null) {
            $auth = $connection->request('authenticate', [
                'methodId' => $methodId,
                '_meta' => ['headless' => true],
            ]);
            if (is_array($auth)) {
                $this->identity = Identity::fromArray($auth);
            }
        }

        return $capabilities;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runCommand(array $arguments, float $timeout): string
    {
        GrokExecutable::assertAvailable($this->binary);

        try {
            $process = new Process([$this->binary, ...$arguments], null, $this->env, null, $timeout);
            $process->mustRun();
        } catch (SymfonyProcessFailed $exception) {
            $failed = $exception->getProcess();
            $output = trim($failed->getOutput());

            throw new ProcessFailed(
                $failed->getExitCode() ?? 1,
                $output !== '' ? $output : $failed->getErrorOutput(),
            );
        } catch (SymfonyRuntimeException $exception) {
            throw new GrokNotFound(sprintf('Grok Build binary "%s" was not found.', $this->binary), 0, $exception);
        }

        return $process->getOutput();
    }
}
