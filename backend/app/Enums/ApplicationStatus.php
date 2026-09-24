<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
}
