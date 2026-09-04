<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Value;

use Develate\GrokCli\Value\Usage;
use Develate\GrokCli\Value\UsageSource;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function test_headless_input_tokens_are_uncached(): void
    {
        $usage = Usage::fromArray([
            'input_tokens' => 100,
            'cache_read_input_tokens' => 400,
            'cache_creation_input_tokens' => 20,
            'output_tokens' => 30,
        ], UsageSource::Headless);

        $this->assertSame(100, $usage->uncachedInputTokens());
        $this->assertEqualsWithDelta(400 / 520, $usage->cacheHitRatio(), 0.0001);
        $this->assertSame(UsageSource::Headless, $usage->source);
    }

    public function test_acp_input_tokens_include_cache(): void
    {
        $usage = Usage::fromArray([
            'inputTokens' => 500,
            'cachedReadTokens' => 400,
            'cacheCreationTokens' => 0,
            'outputTokens' => 20,
        ], UsageSource::Acp);

        $this->assertSame(100, $usage->uncachedInputTokens());
        $this->assertSame(UsageSource::Acp, $usage->source);
    }
}
