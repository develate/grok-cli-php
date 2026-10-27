# Grok Build CLI for PHP

A PHP SDK for controlling [Grok Build](https://docs.x.ai/build/overview) from another application. Persistent agent work uses the Agent Client Protocol over `grok agent stdio`. One-shot and CI work uses headless `grok -p`.

```text
Grok
├── Session     ACP, long-lived
├── Headless    grok -p
└── Billing     _x.ai/billing
```

## Requirements

- PHP 8.2 or newer
- Grok Build installed and authenticated (`grok login` or `XAI_API_KEY`)

```bash
composer require develate/grok-cli-php
```

## ACP session

```php
use Develate\GrokCli\Grok;
use Develate\GrokCli\Update\AgentMessageChunk;
use Develate\GrokCli\Update\ToolCall;
use Develate\GrokCli\Value\PermissionMode;
use Develate\GrokCli\Value\ReasoningEffort;
use Develate\GrokCli\Value\SandboxProfile;

$grok = new Grok();

$session = $grok->session(
    cwd: '/var/www/app',
    model: 'grok-4.7',
    reasoningEffort: ReasoningEffort::High,
    permissionMode: PermissionMode::DontAsk,
    sandbox: SandboxProfile::Strict,
);

$run = $session->stream('Fix the failing tests.');

foreach ($run as $update) {
    if ($update instanceof AgentMessageChunk) {
        echo $update->text;
    }
    if ($update instanceof ToolCall) {
        echo $update->title.PHP_EOL;
    }
}

$result = $run->result();
echo $result->text;
echo $result->usage->inputTokens;
echo $result->cost?->usd;
echo $result->model; // the model the run executed on
```

`$session->prompt('...')` drains the stream and returns the `Result`. Follow-up prompts reuse the same ACP session. Resume with `$grok->resume($sessionId, $cwd)` and branch with `$session->fork()`.

Unknown ACP updates become `UnknownUpdate`. Extra fields never fail parsing.

## Headless

```php
$result = $grok->run(
    prompt: 'Review this repository.',
    cwd: '/project',
    maxTurns: 10,
    schema: $schema,
);
```

Headless uses `--output-format streaming-messages-json`. Usage here is Grok's spend-normalized shape (`UsageSource::Headless`); ACP usage keeps the prompt-level totals (`UsageSource::Acp`).

## Billing

```php
$billing = $grok->billing();
echo $billing->remainingIncludedPercent();
echo $billing->subscriptionTier;
```

Billing is read through the first-party `_x.ai/billing` ACP extension, never by calling private xAI HTTP endpoints.

## Process environment

One environment applies to `--version`, ACP and headless, so a host managing several accounts can pin `GROK_HOME` per identity:

```php
$grok = new Grok(
    binary: '/opt/homebrew/bin/grok',
    env: [
        'GROK_HOME' => '/var/lib/app/accounts/one',
        'XAI_API_KEY' => false,
    ],
);
```

A `false` value removes an inherited variable.
