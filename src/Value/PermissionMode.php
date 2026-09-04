<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum PermissionMode: string
{
    case Default = 'default';
    case AcceptEdits = 'acceptEdits';
    case Plan = 'plan';
    case Auto = 'auto';
    case DontAsk = 'dontAsk';
    case BypassPermissions = 'bypassPermissions';

    public function isAlwaysApprove(): bool
    {
        return $this === self::BypassPermissions;
    }

    public function isAuto(): bool
    {
        return $this === self::Auto;
    }
}
