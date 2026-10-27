<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests;

use Develate\GrokCli\ResultBuilder;
use Develate\GrokCli\Value\RunMetadata;
use Develate\GrokCli\Value\UsageSource;
use PHPUnit\Framework\TestCase;

final class ResultBuilderTest extends TestCase
{
    public function test_prompt_meta_model_id_becomes_the_result_model(): void
    {
        $result = (new ResultBuilder('session-one'))->build(
            ['stopReason' => 'end_turn', '_meta' => ['modelId' => 'grok-4.6']],
            $this->metadata(),
        );

        $this->assertSame('grok-4.6', $result->model);
    }

    public function test_headless_model_fills_in_when_the_result_names_none(): void
    {
        $result = (new ResultBuilder('session-one'))->build(
            ['stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 2]],
            $this->metadata(),
            UsageSource::Headless,
            'grok-4.5',
        );

        $this->assertSame('grok-4.5', $result->model);
    }

    public function test_prompt_meta_wins_over_the_stream_model(): void
    {
        $result = (new ResultBuilder('session-one'))->build(
            ['stopReason' => 'end_turn', '_meta' => ['modelId' => 'grok-4.6']],
            $this->metadata(),
            UsageSource::Headless,
            'grok-4.5',
        );

        $this->assertSame('grok-4.6', $result->model);
    }

    public function test_missing_model_stays_null(): void
    {
        $result = (new ResultBuilder('session-one'))->build(
            ['stopReason' => 'end_turn'],
            $this->metadata(),
        );

        $this->assertNull($result->model);
    }

    private function metadata(): RunMetadata
    {
        return new RunMetadata('1.0.13', 'acp', 'grok-4.6', '/tmp');
    }
}
