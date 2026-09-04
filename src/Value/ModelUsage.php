<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class ModelUsage
{
    public function __construct(
        public string $model,
        public int $inputTokens = 0,
        public int $cacheReadInputTokens = 0,
        public int $cacheCreationInputTokens = 0,
        public int $outputTokens = 0,
        public int $reasoningTokens = 0,
        public int $totalTokens = 0,
        public int $modelCalls = 0,
        public ?int $costUsdTicks = null,
        public ?string $costUsd = null,
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $model, array $data): self
    {
        $ticks = isset($data['costUsdTicks']) && is_numeric($data['costUsdTicks'])
            ? (int) $data['costUsdTicks']
            : (isset($data['cost_usd_ticks']) && is_numeric($data['cost_usd_ticks']) ? (int) $data['cost_usd_ticks'] : null);
        $usd = $data['costUSD'] ?? $data['costUsd'] ?? $data['cost_usd'] ?? null;

        return new self(
            model: $model,
            inputTokens: self::integer($data, 'input_tokens', 'inputTokens'),
            cacheReadInputTokens: self::integer($data, 'cache_read_input_tokens', 'cacheReadInputTokens', 'cachedReadTokens'),
            cacheCreationInputTokens: self::integer($data, 'cache_creation_input_tokens', 'cacheCreationInputTokens', 'cacheCreationTokens'),
            outputTokens: self::integer($data, 'output_tokens', 'outputTokens'),
            reasoningTokens: self::integer($data, 'reasoning_tokens', 'reasoningTokens'),
            totalTokens: self::integer($data, 'total_tokens', 'totalTokens'),
            modelCalls: self::integer($data, 'model_calls', 'modelCalls'),
            costUsdTicks: $ticks,
            costUsd: is_numeric($usd) ? (string) $usd : (is_string($usd) ? $usd : null),
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, self>
     */
    public static function map(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        $map = [];
        foreach ($data as $model => $usage) {
            if (is_string($model) && is_array($usage)) {
                $map[$model] = self::fromArray($model, $usage);
            }
        }

        return $map;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string ...$keys): int
    {
        foreach ($keys as $key) {
            if (is_numeric($data[$key] ?? null)) {
                return (int) $data[$key];
            }
        }

        return 0;
    }
}
