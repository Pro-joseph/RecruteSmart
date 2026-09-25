<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationEventType: string
{
    case StatusChanged = 'status_changed';
    case NoteAdded = 'note_added';
    case InterviewPlanned = 'interview_planned';
    case Forwarded = 'forwarded';
    case AnalysisCompleted = 'analysis_completed';
}
