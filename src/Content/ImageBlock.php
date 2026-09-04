<?php

declare(strict_types=1);

namespace Develate\GrokCli\Content;

final readonly class ImageBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $mimeType,
        public string $data,
        public ?string $path = null,
        private array $raw = [],
    ) {
    }

    public static function fromPath(string $path): self
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf('Unable to read image "%s".', $path));
        }

        $mime = self::mimeType($path);

        return new self($mime, base64_encode($contents), $path);
    }

    public function raw(): array
    {
        return $this->raw !== [] ? $this->raw : $this->toPrompt();
    }

    public function toPrompt(): array
    {
        return [
            'type' => 'image',
            'mimeType' => $this->mimeType,
            'data' => $this->data,
        ];
    }

    /**
     * ACP resource content, used when the agent does not advertise image prompts.
     *
     * @return array<string, mixed>
     */
    public function toResource(): array
    {
        $uri = $this->path !== null
            ? (str_starts_with($this->path, 'file:') ? $this->path : 'file://'.$this->path)
            : 'data:'.$this->mimeType.';base64,'.$this->data;

        return [
            'type' => 'resource',
            'resource' => [
                'uri' => $uri,
                'mimeType' => $this->mimeType,
                'blob' => $this->data,
            ],
        ];
    }

    private static function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
