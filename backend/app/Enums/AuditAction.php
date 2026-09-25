<?php

declare(strict_types=1);

namespace App\Enums;

/** Sensitive actions recorded in audit_logs (spec §4.2, EF-1204). */
enum AuditAction: string
{
    case CvViewed = 'cv_viewed';
    case CvDownloaded = 'cv_downloaded';
    case ForwardSent = 'forward_sent';
    case ApplicationDeleted = 'application_deleted';
    case ApplicationPurged = 'application_purged';
    case ApplicationExported = 'application_exported';
    case LinkRegenerated = 'link_regenerated';
}
