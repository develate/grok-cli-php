<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum SessionCapability: string
{
    case Load = 'loadSession';
    case List = 'list';
    case Resume = 'resume';
    case Close = 'close';
    case Cancel = 'cancel';
}
