<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewType: string
{
    case Phone = 'phone';
    case Video = 'video';
    case Onsite = 'onsite';
}
