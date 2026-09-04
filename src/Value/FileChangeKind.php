<?php

declare(strict_types=1);

namespace Develate\GrokCli\Value;

enum FileChangeKind: string
{
    case Add = 'add';
    case Update = 'update';
    case Delete = 'delete';
}
