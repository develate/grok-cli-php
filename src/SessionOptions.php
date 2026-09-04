<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Value\PermissionMode;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\SandboxProfile;

final readonly class SessionOptions
{
    /**
     * @param  list<string>  $allow
     * @param  list<string>  $deny
     * @param  list<array<string, mixed>>  $mcpServers
     * @param  list<string>|null  $tools
     * @param  list<string>|null  $disallowedTools
     */
    public function __construct(
        public string $cwd,
        public ?string $model = null,
        public ?ReasoningEffort $reasoningEffort = null,
        public ?PermissionMode $permissionMode = null,
        public ?SandboxProfile $sandbox = null,
        public array $allow = [],
        public array $deny = [],
        public array $mcpServers = [],
        public ?string $rules = null,
        public ?string $systemPromptOverride = null,
        public ?array $tools = null,
        public ?array $disallowedTools = null,
        public bool $plan = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionMeta(): array
    {
        $meta = [];
        if ($this->permissionMode?->isAlwaysApprove()) {
            $meta['yoloMode'] = true;
        } elseif ($this->permissionMode?->isAuto()) {
            $meta['autoMode'] = true;
        }
        if ($this->rules !== null && $this->rules !== '') {
            $meta['rules'] = $this->rules;
        }
        if ($this->systemPromptOverride !== null && $this->systemPromptOverride !== '') {
            $meta['systemPromptOverride'] = $this->systemPromptOverride;
        }

        return $meta;
    }

    /**
     * @return list<string>
     */
    public function agentArguments(): array
    {
        $arguments = [];
        if ($this->model !== null && $this->model !== '') {
            $arguments[] = '--model';
            $arguments[] = $this->model;
        }
        if ($this->reasoningEffort !== null) {
            $arguments[] = '--reasoning-effort';
            $arguments[] = $this->reasoningEffort->value;
        }
        if ($this->permissionMode?->isAlwaysApprove()) {
            $arguments[] = '--always-approve';
        }

        return $arguments;
    }
}
