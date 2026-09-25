<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewDecision: string
{
    case Proceed = 'proceed';
    case Reject = 'reject';
}
