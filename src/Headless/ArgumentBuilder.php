<?php

declare(strict_types=1);

namespace Develate\GrokCli\Headless;

use Develate\GrokCli\Content\ImageBlock;
use Develate\GrokCli\Prompt;
use Develate\GrokCli\RunOptions;
use Develate\GrokCli\SessionOptions;
use Develate\GrokCli\Value\PermissionMode;

final class ArgumentBuilder
{
    /**
     * @return list<string>
     */
    public function command(
        string $binary,
        Prompt $prompt,
        SessionOptions $session,
        ?RunOptions $run = null,
        ?string $resumeSessionId = null,
        bool $continue = false,
        bool $fork = false,
    ): array {
        $command = [$binary, '--no-auto-update', '--output-format', 'streaming-messages-json', '--include-partial-messages'];

        $hasImages = false;
        foreach ($prompt->blocks() as $block) {
            if ($block instanceof ImageBlock) {
                $hasImages = true;
                break;
            }
        }

        if ($hasImages || count($prompt->blocks()) > 1) {
            $command[] = '--prompt-json';
            $command[] = json_encode($prompt->toContent(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } else {
            $command[] = '-p';
            $command[] = $prompt->textContent();
        }

        $command[] = '--cwd';
        $command[] = $session->cwd;

        if ($session->model !== null && $session->model !== '') {
            $command[] = '--model';
            $command[] = $session->model;
        }
        if ($session->reasoningEffort !== null) {
            $command[] = '--reasoning-effort';
            $command[] = $session->reasoningEffort->value;
        }
        if ($session->sandbox !== null) {
            $command[] = '--sandbox';
            $command[] = $session->sandbox->value;
        }

        $permission = $session->plan ? PermissionMode::Plan : $session->permissionMode;
        if ($permission === PermissionMode::BypassPermissions) {
            $command[] = '--always-approve';
        } elseif ($permission !== null) {
            $command[] = '--permission-mode';
            $command[] = $permission->value;
        }

        foreach ($session->allow as $rule) {
            $command[] = '--allow';
            $command[] = $rule;
        }
        foreach ($session->deny as $rule) {
            $command[] = '--deny';
            $command[] = $rule;
        }
        if ($session->tools !== null) {
            $command[] = '--tools';
            $command[] = implode(',', $session->tools);
        }
        if ($session->disallowedTools !== null) {
            $command[] = '--disallowed-tools';
            $command[] = implode(',', $session->disallowedTools);
        }
        if ($session->rules !== null && $session->rules !== '') {
            $command[] = '--rules';
            $command[] = $session->rules;
        }
        if ($session->systemPromptOverride !== null && $session->systemPromptOverride !== '') {
            $command[] = '--system-prompt-override';
            $command[] = $session->systemPromptOverride;
        }

        if ($resumeSessionId !== null && $resumeSessionId !== '') {
            $command[] = '--resume';
            $command[] = $resumeSessionId;
            if ($fork) {
                $command[] = '--fork-session';
            }
        } elseif ($continue) {
            $command[] = '--continue';
        }

        if ($run?->maxTurns !== null) {
            $command[] = '--max-turns';
            $command[] = (string) $run->maxTurns;
        }
        if ($run?->schema !== null) {
            $command[] = '--json-schema';
            $command[] = json_encode($run->schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        return $command;
    }
}
