<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $cacheReadInputTokens = 0,
        public int $cacheCreationInputTokens = 0,
        public int $outputTokens = 0,
        public int $reasoningTokens = 0,
        public int $totalTokens = 0,
        public bool $incomplete = false,
        public UsageSource $source = UsageSource::Acp,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data, UsageSource $source = UsageSource::Acp, bool $incomplete = false): self
    {
        $data ??= [];
        $input = self::integer($data, 'input_tokens', 'inputTokens');
        $cacheRead = self::integer($data, 'cache_read_input_tokens', 'cacheReadInputTokens', 'cachedReadTokens');
        $cacheCreate = self::integer($data, 'cache_creation_input_tokens', 'cacheCreationInputTokens', 'cacheCreationTokens');
        $output = self::integer($data, 'output_tokens', 'outputTokens');
        $reasoning = self::integer($data, 'reasoning_tokens', 'reasoningTokens');
        $total = self::integer($data, 'total_tokens', 'totalTokens');

        if ($total === 0) {
            $total = $input + $cacheRead + $cacheCreate + $output;
            if ($source === UsageSource::Acp && $total === $input + $output && $cacheRead > 0 && $input >= $cacheRead) {
                $total = $input + $output;
            }
        }

        $incomplete = $incomplete
            || self::bool($data, 'usage_is_incomplete', 'usageIsIncomplete', 'incomplete');

        return new self(
            inputTokens: $input,
            cacheReadInputTokens: $cacheRead,
            cacheCreationInputTokens: $cacheCreate,
            outputTokens: $output,
            reasoningTokens: $reasoning,
            totalTokens: $total,
            incomplete: $incomplete,
            source: $source,
        );
    }

    public function uncachedInputTokens(): int
    {
        if ($this->source === UsageSource::Headless) {
            return $this->inputTokens;
        }

        return max(0, $this->inputTokens - $this->cacheReadInputTokens - $this->cacheCreationInputTokens);
    }

    public function cacheHitRatio(): float
    {
        $input = $this->source === UsageSource::Headless
            ? $this->inputTokens + $this->cacheReadInputTokens + $this->cacheCreationInputTokens
            : max($this->inputTokens, $this->cacheReadInputTokens + $this->cacheCreationInputTokens);

        return $input === 0 ? 0.0 : $this->cacheReadInputTokens / $input;
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
