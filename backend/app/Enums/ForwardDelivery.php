<?php

declare(strict_types=1);

namespace App\Enums;

enum ForwardDelivery: string
{
    case Attachments = 'attachments';
    case Links = 'links';
}
