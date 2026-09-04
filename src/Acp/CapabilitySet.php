<?php

declare(strict_types=1);

namespace Develate\GrokCli\Acp;

use Develate\GrokCli\Value\ModelInfo;
use Develate\GrokCli\Value\SessionCapability;

final readonly class CapabilitySet
{
    /**
     * @param  array<string, mixed>  $agent
     * @param  list<array<string, mixed>>  $authMethods
     * @param  array<string, mixed>  $meta
     * @param  list<ModelInfo>  $models
     */
    public function __construct(
        public int $protocolVersion,
        public array $agent,
        public array $authMethods,
        public array $meta,
        public array $models,
        public ?string $currentModelId,
        public ?string $agentVersion,
        public ?string $defaultAuthMethodId,
    ) {
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function fromInitialize(array $result): self
    {
        $meta = is_array($result['_meta'] ?? null) ? $result['_meta'] : [];
        $agent = is_array($result['agentCapabilities'] ?? null) ? $result['agentCapabilities'] : [];
        $authMethods = [];
        foreach ($result['authMethods'] ?? [] as $method) {
            if (is_array($method)) {
                $authMethods[] = $method;
            }
        }

        $modelState = is_array($meta['modelState'] ?? null) ? $meta['modelState'] : [];
        $models = [];
        foreach ($modelState['availableModels'] ?? [] as $model) {
            if (is_array($model)) {
                $models[] = ModelInfo::fromArray($model);
            }
        }

        $defaultAuth = $meta['defaultAuthMethodId'] ?? ($authMethods[0]['id'] ?? null);

        return new self(
            protocolVersion: is_numeric($result['protocolVersion'] ?? null) ? (int) $result['protocolVersion'] : 1,
            agent: $agent,
            authMethods: $authMethods,
            meta: $meta,
            models: $models,
            currentModelId: isset($modelState['currentModelId']) && is_string($modelState['currentModelId'])
                ? $modelState['currentModelId']
                : null,
            agentVersion: isset($meta['agentVersion']) && is_string($meta['agentVersion']) ? $meta['agentVersion'] : null,
            defaultAuthMethodId: is_string($defaultAuth) ? $defaultAuth : null,
        );
    }

    public function supports(SessionCapability $capability): bool
    {
        return match ($capability) {
            SessionCapability::Load => (bool) ($this->agent['loadSession'] ?? false),
            SessionCapability::List => isset($this->agent['sessionCapabilities']['list']),
            SessionCapability::Resume => isset($this->agent['sessionCapabilities']['resume']) || (bool) ($this->agent['loadSession'] ?? false),
            SessionCapability::Close => isset($this->agent['sessionCapabilities']['close']),
            SessionCapability::Cancel => true,
        };
    }

    public function supportsExtension(string $name): bool
    {
        $needle = ltrim($name, '_');
        $agentMeta = is_array($this->agent['_meta'] ?? null) ? $this->agent['_meta'] : [];

        foreach ([$this->meta, $agentMeta] as $bag) {
            if (array_key_exists($name, $bag) || array_key_exists($needle, $bag) || array_key_exists('_'.$needle, $bag)) {
                return true;
            }
        }

        return str_starts_with($needle, 'x.ai/');
    }

    public function supportsImagePrompts(): bool
    {
        $prompt = is_array($this->agent['promptCapabilities'] ?? null) ? $this->agent['promptCapabilities'] : [];

        return (bool) ($prompt['image'] ?? false);
    }

    public function supportsEmbeddedContext(): bool
    {
        $prompt = is_array($this->agent['promptCapabilities'] ?? null) ? $this->agent['promptCapabilities'] : [];

        return (bool) ($prompt['embeddedContext'] ?? false);
    }
}
