<?php

namespace App\Enums;

enum ApplicationActivityType: string
{
    case TrackingStarted = 'tracking_started';
    case ApplicationCreated = 'application_created';
    case StatusChanged = 'status_changed';
    case ApplicationUpdated = 'application_updated';
    case InterviewScheduled = 'interview_scheduled';
    case InterviewRescheduled = 'interview_rescheduled';
    case InterviewOutcomeRecorded = 'interview_outcome_recorded';
    case InterviewUpdated = 'interview_updated';
    case InterviewDeleted = 'interview_deleted';
    case CommentAdded = 'comment_added';
}
