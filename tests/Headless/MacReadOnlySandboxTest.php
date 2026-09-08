<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Headless;

use Develate\GrokCli\Grok;
use Develate\GrokCli\Headless\MacReadOnlySandbox;
use Develate\GrokCli\Process\GrokProcess;
use Develate\GrokCli\Process\ProcessFactory;
use Develate\GrokCli\Value\SandboxProfile;
use PHPUnit\Framework\TestCase;

final class MacReadOnlySandboxTest extends TestCase
{
    public function test_a_read_only_generation_uses_the_compatibility_profile_on_an_affected_mac(): void
    {
        if (! (new MacReadOnlySandbox)->applicable('1.0.13', [])) {
            $this->markTestSkipped('Requires macOS with the standard Docker Desktop socket symlink.');
        }
        $directory = sys_get_temp_dir().'/grok-headless-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $processes = new class extends ProcessFactory
        {
            public array $command = [];

            public function create(array $command, ?string $cwd = null, ?float $timeout = null, array $env = [], ?string $input = null): GrokProcess
            {
                $this->command = $command;

                return new GrokProcess([PHP_BINARY, '-r', 'echo json_encode(["type" => "result", "result" => "fix: correct total", "session_id" => "test"]).PHP_EOL;']);
            }
        };

        try {
            $result = (new Grok(binary: PHP_BINARY, env: ['GROK_HOME' => $directory], version: '1.0.13', processes: $processes))->run(
                prompt: 'Write a commit message.',
                cwd: $directory,
                sandbox: SandboxProfile::ReadOnly,
            );

            $this->assertSame('fix: correct total', $result->text);
            $position = array_search('--sandbox', $processes->command, true);
            $this->assertNotFalse($position);
            $name = $processes->command[$position + 1];
            $this->assertStringStartsWith('grok-cli-php-read-only-macos-', $name);
            $this->assertStringContainsString('[profiles.'.$name.']', file_get_contents($directory.'/sandbox.toml'));
        } finally {
            if (is_file($directory.'/sandbox.toml')) {
                unlink($directory.'/sandbox.toml');
            }
            rmdir($directory);
        }
    }

    public function test_future_versions_keep_the_native_sandbox(): void
    {
        $this->assertFalse((new MacReadOnlySandbox)->applicable('1.0.14', []));
    }

    public function test_it_refuses_a_conflicting_profile_without_overwriting_it(): void
    {
        $directory = sys_get_temp_dir().'/grok-conflict-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        try {
            $sandbox = new MacReadOnlySandbox;
            $name = $sandbox->install($directory);
            $conflict = '[profiles.'.$name."]\nextends = \"workspace\"\n";
            file_put_contents($directory.'/sandbox.toml', $conflict);
            try {
                $sandbox->install($directory);
                $this->fail('Expected a conflicting profile to be rejected.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('has been modified', $exception->getMessage());
                $this->assertSame($conflict, file_get_contents($directory.'/sandbox.toml'));
            }
        } finally {
            unlink($directory.'/sandbox.toml');
            rmdir($directory);
        }
    }

    public function test_it_preserves_user_profiles_and_installs_only_once(): void
    {
        $directory = sys_get_temp_dir().'/grok-profile-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $original = "[profiles.mine]\nextends = \"strict\"\n";
        file_put_contents($directory.'/sandbox.toml', $original);

        try {
            $sandbox = new MacReadOnlySandbox;
            $name = $sandbox->install($directory);
            $contents = file_get_contents($directory.'/sandbox.toml');
            $this->assertSame($name, $sandbox->install($directory));
            $this->assertSame($contents, file_get_contents($directory.'/sandbox.toml'));
            $this->assertStringStartsWith($original, $contents);
            $this->assertStringContainsString('[profiles.'.$name.']', $contents);
            $this->assertStringContainsString('extends = "read-only"', $contents);
            $this->assertStringContainsString('/var/run/**', $contents);
            $this->assertStringContainsString('/**/.docker/**', $contents);
        } finally {
            unlink($directory.'/sandbox.toml');
            rmdir($directory);
        }
    }
}
