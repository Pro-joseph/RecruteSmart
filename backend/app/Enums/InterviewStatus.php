<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Canceled = 'canceled';
    case Rescheduled = 'rescheduled';
}
