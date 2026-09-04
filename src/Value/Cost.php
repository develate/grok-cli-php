<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class Cost
{
    public const TICKS_PER_USD = 10_000_000_000;

    public function __construct(
        public string $usd,
        public ?int $ticks,
        public bool $complete,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $usageIncomplete = false): ?self
    {
        if (self::bool($data, 'cost_is_partial', 'costIsPartial') || $usageIncomplete) {
            return null;
        }

        $ticks = isset($data['total_cost_usd_ticks']) && is_numeric($data['total_cost_usd_ticks'])
            ? (int) $data['total_cost_usd_ticks']
            : (isset($data['costUsdTicks']) && is_numeric($data['costUsdTicks']) ? (int) $data['costUsdTicks'] : null);
        $usd = $data['total_cost_usd'] ?? $data['totalCostUsd'] ?? $data['costUsd'] ?? null;

        if ($usd === null && $ticks === null) {
            return null;
        }

        if ($usd === null && $ticks !== null) {
            $usd = (string) ($ticks / self::TICKS_PER_USD);
        }

        return new self(
            usd: is_numeric($usd) || is_string($usd) ? (string) $usd : '0',
            ticks: $ticks,
            complete: true,
        );
    }

    /** @param array<string, mixed> $data */
    private static function bool(array $data, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return (bool) $data[$key];
            }
        }

        return false;
    }
}
