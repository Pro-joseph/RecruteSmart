<?php

declare(strict_types=1);

namespace App\Enums;

enum AtsVerdict: string
{
    case Conforming = 'conforming';
    case NeedsImprovement = 'needs_improvement';
    case NonCompliant = 'non_compliant';
}
