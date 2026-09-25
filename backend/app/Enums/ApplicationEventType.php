<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationEventType: string
{
    case StatusChanged = 'status_changed';
    case NoteAdded = 'note_added';
    case InterviewPlanned = 'interview_planned';
    case InterviewUpdated = 'interview_updated';
    case Forwarded = 'forwarded';
    case ForwardCreated = 'forward_created';
    case AnalysisCompleted = 'analysis_completed';
}
