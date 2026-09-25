<?php

declare(strict_types=1);

namespace App\Enums;

enum AtsVerdict: string
{
    case Compliant = 'compliant';
    case Improvable = 'improvable';
    case NonCompliant = 'non_compliant';
}
