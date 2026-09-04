<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum ReasoningEffort: string
{
    case None = 'none';
    case Minimal = 'minimal';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case ExtraHigh = 'xhigh';
    case Max = 'max';
}
