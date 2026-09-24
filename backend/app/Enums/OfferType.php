<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Internship = 'internship';
    case Apprenticeship = 'apprenticeship';
    case Freelance = 'freelance';
    case Other = 'other';
}
