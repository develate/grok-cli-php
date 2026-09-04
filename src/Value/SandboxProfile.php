<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum SandboxProfile: string
{
    case Off = 'off';
    case Workspace = 'workspace';
    case Devbox = 'devbox';
    case ReadOnly = 'read-only';
    case Strict = 'strict';
}
