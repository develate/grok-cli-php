<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Headless;

use Develate\GrokCli\Headless\ArgumentBuilder;
use Develate\GrokCli\Prompt;
use Develate\GrokCli\RunOptions;
use Develate\GrokCli\SessionOptions;
use Develate\GrokCli\Value\PermissionMode;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\SandboxProfile;
use PHPUnit\Framework\TestCase;

final class ArgumentBuilderTest extends TestCase
{
    public function test_it_builds_a_headless_command(): void
    {
        $command = (new ArgumentBuilder)->command(
            binary: 'grok',
            prompt: Prompt::text('Review this repository.'),
            session: new SessionOptions(
                cwd: '/project',
                model: 'grok-4.6',
                reasoningEffort: ReasoningEffort::High,
                permissionMode: PermissionMode::BypassPermissions,
                sandbox: SandboxProfile::Strict,
                allow: ['Read'],
                deny: ['Bash(rm -rf *)'],
            ),
            run: new RunOptions(maxTurns: 10, schema: ['type' => 'object']),
        );

        $this->assertSame('grok', $command[0]);
        $this->assertContains('--output-format', $command);
        $this->assertContains('streaming-messages-json', $command);
        $this->assertContains('-p', $command);
        $this->assertContains('Review this repository.', $command);
        $this->assertContains('--always-approve', $command);
        $this->assertContains('--sandbox', $command);
        $this->assertContains('strict', $command);
        $this->assertContains('--max-turns', $command);
        $this->assertContains('10', $command);
        $this->assertContains('--json-schema', $command);
    }
}
