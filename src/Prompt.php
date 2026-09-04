<?php

declare(strict_types=1);

namespace Develate\GrokCli;

use Develate\GrokCli\Content\ContentBlock;
use Develate\GrokCli\Content\ImageBlock;
use Develate\GrokCli\Content\TextBlock;

final class Prompt
{
    /** @var list<ContentBlock> */
    private array $blocks = [];

    public static function create(): self
    {
        return new self;
    }

    public static function text(string $text): self
    {
        return self::create()->withText($text);
    }

    /**
     * @param  list<string>  $images
     */
    public static function from(string $text, array $images = []): self
    {
        $prompt = self::text($text);
        foreach ($images as $image) {
            $prompt = $prompt->image($image);
        }

        return $prompt;
    }

    public function withText(string $text): self
    {
        $clone = clone $this;
        $clone->blocks[] = new TextBlock($text);

        return $clone;
    }

    public function image(string $path): self
    {
        $clone = clone $this;
        $clone->blocks[] = ImageBlock::fromPath($path);

        return $clone;
    }

    public function with(ContentBlock $block): self
    {
        $clone = clone $this;
        $clone->blocks[] = $block;

        return $clone;
    }

    /** @return list<ContentBlock> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function textContent(): string
    {
        $parts = [];
        foreach ($this->blocks as $block) {
            if ($block instanceof TextBlock) {
                $parts[] = $block->text;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toContent(bool $imagesAsResources = false): array
    {
        $content = [];
        foreach ($this->blocks as $block) {
            if ($imagesAsResources && $block instanceof ImageBlock) {
                $content[] = $block->toResource();
                continue;
            }
            $content[] = $block->toPrompt();
        }

        return $content;
    }
}
