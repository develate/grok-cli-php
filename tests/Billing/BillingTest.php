<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tests\Billing;

use Develate\GrokCli\Billing\Billing;
use Develate\GrokCli\Grok;
use Develate\GrokCli\Tests\Support\FakeConnection;
use PHPUnit\Framework\TestCase;

final class BillingTest extends TestCase
{
    public function test_billing_is_read_through_the_acp_extension(): void
    {
        $connection = new FakeConnection;
        $billing = (new Grok(connection: $connection))->billing();

        $this->assertSame(42.5, $billing->usedIncludedPercent);
        $this->assertSame(57.5, $billing->remainingIncludedPercent());
        $this->assertSame('SuperGrok', $billing->subscriptionTier);
        $this->assertTrue($billing->unified);
        $this->assertSame('1250', $billing->prepaidBalance);
        $this->assertSame('USAGE_PERIOD_TYPE_WEEKLY', $billing->period?->type);
        $this->assertContains('_x.ai/billing', array_column($connection->requests, 0));
    }

    public function test_legacy_monthly_limit_is_converted_to_a_percent(): void
    {
        $billing = Billing::fromArray([
            'config' => [
                'monthlyLimit' => ['val' => 2000],
                'used' => ['val' => 500],
                'billingPeriodStart' => '2026-04-01T00:00:00Z',
                'billingPeriodEnd' => '2026-05-01T00:00:00Z',
            ],
        ]);

        $this->assertSame(25.0, $billing->usedIncludedPercent);
        $this->assertSame(75.0, $billing->remainingIncludedPercent());
    }
}
