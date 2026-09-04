<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum UsageSource: string
{
    case Acp = 'acp';
    case Headless = 'headless';
}
