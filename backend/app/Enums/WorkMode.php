<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkMode: string
{
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';
    case Remote = 'remote';
}
