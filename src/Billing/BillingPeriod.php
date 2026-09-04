<?php

declare(strict_types=1);

namespace Develate\GrokCli\Billing;

final readonly class BillingPeriod
{
    public function __construct(
        public string $type,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
    ) {
    }
}
