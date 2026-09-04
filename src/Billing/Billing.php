<?php

declare(strict_types=1);

namespace Develate\GrokCli\Billing;

final readonly class Billing
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?float $usedIncludedPercent,
        public ?BillingPeriod $period,
        public ?string $prepaidBalance,
        public ?string $onDemandCap,
        public ?string $onDemandUsed,
        public ?string $subscriptionTier,
        public bool $onDemandEnabled,
        public bool $unified,
        private array $raw = [],
    ) {
    }

    public function remainingIncludedPercent(): ?float
    {
        return $this->usedIncludedPercent === null
            ? null
            : max(0, 100 - $this->usedIncludedPercent);
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : $data;
        $percent = $config['creditUsagePercent'] ?? $config['credit_usage_percent'] ?? null;
        if ($percent === null && isset($config['monthlyLimit'], $config['used'])) {
            $limit = self::cents($config['monthlyLimit']);
            $used = self::cents($config['used']);
            if ($limit !== null && $limit > 0 && $used !== null) {
                $percent = ($used / $limit) * 100;
            }
        }

        $periodData = is_array($config['currentPeriod'] ?? null) ? $config['currentPeriod'] : null;
        $start = $periodData['start'] ?? $config['billingPeriodStart'] ?? $config['billing_period_start'] ?? null;
        $end = $periodData['end'] ?? $config['billingPeriodEnd'] ?? $config['billing_period_end'] ?? null;
        $type = $periodData['type'] ?? $periodData['periodType'] ?? 'unknown';

        $period = null;
        if (is_string($start) && is_string($end) && $start !== '' && $end !== '') {
            try {
                $period = new BillingPeriod(
                    type: is_string($type) ? $type : 'unknown',
                    startsAt: new \DateTimeImmutable($start),
                    endsAt: new \DateTimeImmutable($end),
                );
            } catch (\Exception) {
                $period = null;
            }
        }

        $onDemandEnabled = $data['onDemandEnabled'] ?? $data['on_demand_enabled'] ?? null;
        $unified = $config['isUnifiedBillingUser'] ?? $config['is_unified_billing_user'] ?? false;
        $tier = $data['subscription_tier'] ?? $data['subscriptionTier'] ?? null;

        return new self(
            usedIncludedPercent: is_numeric($percent) ? (float) $percent : null,
            period: $period,
            prepaidBalance: self::money($config['prepaidBalance'] ?? $config['prepaid_balance'] ?? null),
            onDemandCap: self::money($config['onDemandCap'] ?? $config['on_demand_cap'] ?? null),
            onDemandUsed: self::money($config['onDemandUsed'] ?? $config['on_demand_used'] ?? null),
            subscriptionTier: is_string($tier) ? $tier : null,
            onDemandEnabled: $onDemandEnabled === null ? false : (bool) $onDemandEnabled,
            unified: (bool) $unified,
            raw: $data,
        );
    }

    private static function cents(mixed $value): ?int
    {
        if (is_array($value) && isset($value['val']) && is_numeric($value['val'])) {
            return (int) $value['val'];
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function money(mixed $value): ?string
    {
        $cents = self::cents($value);

        return $cents === null ? null : (string) $cents;
    }
}
