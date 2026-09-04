<?php

declare(strict_types=1);

namespace Develate\GrokCli\Tool;

enum ToolCallStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) ? (self::tryFrom($value) ?? self::Pending) : self::Pending;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
