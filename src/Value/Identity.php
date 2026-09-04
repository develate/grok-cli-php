<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

final readonly class Identity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $email,
        public ?string $authMode,
        public ?string $subscriptionTier,
        public ?string $teamId,
        public bool $loggedIn,
        private array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $meta = is_array($data['_meta'] ?? null) ? $data['_meta'] : $data;

        $email = self::string($meta, 'email');
        $mode = self::string($meta, 'auth_mode', 'authMode');

        return new self(
            email: $email,
            authMode: $mode,
            subscriptionTier: self::string($meta, 'subscription_tier', 'subscriptionTier'),
            teamId: self::string($meta, 'team_id', 'teamId'),
            loggedIn: $email !== null || $mode !== null,
            raw: $data,
        );
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data */
    private static function string(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
