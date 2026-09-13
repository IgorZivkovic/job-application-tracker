<?php

namespace App\Services;

use App\Enums\ApplicationActivityType;
use App\Models\ApplicationActivity;
use App\Models\AuthUser;
use App\Models\Interview;
use App\Models\JobApplication;
use Carbon\CarbonInterface;
use UnitEnum;

class ApplicationActivityRecorder
{
    /**
     * @return array<string, mixed>
     */
    public function applicationSnapshot(JobApplication $application): array
    {
        $application->loadMissing('company:id,name');

        return [
            'company' => [
                'id' => $application->company->id,
                'name' => $application->company->name,
            ],
            'position' => $application->position,
            'status' => $this->normalize($application->status),
        ];
    }

    public function recordApplicationCreated(JobApplication $application, AuthUser $actor): ApplicationActivity
    {
        return $this->record(
            $application,
            $actor,
            ApplicationActivityType::ApplicationCreated,
            ['application' => $this->applicationSnapshot($application)],
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordApplicationChanges(
        JobApplication $application,
        AuthUser $actor,
        array $before,
    ): void {
        $after = $this->applicationSnapshot($application);

        if ($before['status'] !== $after['status']) {
            $this->record($application, $actor, ApplicationActivityType::StatusChanged, [
                'from_status' => $before['status'],
                'to_status' => $after['status'],
            ]);
        }

        $changes = $this->changes(
            array_diff_key($before, ['status' => true]),
            array_diff_key($after, ['status' => true]),
        );

        if ($changes !== []) {
            $this->record($application, $actor, ApplicationActivityType::ApplicationUpdated, [
                'changes' => $changes,
            ]);
        }
    }

    public function recordStatusChanged(
        JobApplication $application,
        AuthUser $actor,
        string $fromStatus,
        string $toStatus,
    ): ApplicationActivity {
        return $this->record($application, $actor, ApplicationActivityType::StatusChanged, [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }

    public function recordInterviewScheduled(
        JobApplication $application,
        Interview $interview,
        AuthUser $actor,
    ): ApplicationActivity {
        return $this->record($application, $actor, ApplicationActivityType::InterviewScheduled, [
            'interview' => $this->interviewSnapshot($interview),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function recordInterviewChanges(
        JobApplication $application,
        Interview $interview,
        AuthUser $actor,
        array $before,
    ): void {
        $after = $this->interviewSnapshot($interview);

        if ($before['scheduled_at'] !== $after['scheduled_at']) {
            $this->record($application, $actor, ApplicationActivityType::InterviewRescheduled, [
                'interview_id' => $interview->id,
                'interview_type' => $after['type'],
                'from_scheduled_at' => $before['scheduled_at'],
                'to_scheduled_at' => $after['scheduled_at'],
            ]);
        }

        if ($before['outcome'] !== $after['outcome']) {
            $this->record($application, $actor, ApplicationActivityType::InterviewOutcomeRecorded, [
                'interview_id' => $interview->id,
                'interview_type' => $after['type'],
                'from_outcome' => $before['outcome'],
                'to_outcome' => $after['outcome'],
            ]);
        }

        $changes = $this->changes(
            array_diff_key($before, ['scheduled_at' => true, 'outcome' => true]),
            array_diff_key($after, ['scheduled_at' => true, 'outcome' => true]),
        );

        if ($changes !== []) {
            $this->record($application, $actor, ApplicationActivityType::InterviewUpdated, [
                'interview_id' => $interview->id,
                'changes' => $changes,
            ]);
        }
    }

    public function recordInterviewDeleted(
        JobApplication $application,
        Interview $interview,
        AuthUser $actor,
    ): ApplicationActivity {
        return $this->record($application, $actor, ApplicationActivityType::InterviewDeleted, [
            'interview' => $this->interviewSnapshot($interview),
        ]);
    }

    public function recordComment(
        JobApplication $application,
        AuthUser $actor,
        string $comment,
    ): ApplicationActivity {
        return $this->record(
            $application,
            $actor,
            ApplicationActivityType::CommentAdded,
            comment: $comment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function interviewSnapshot(Interview $interview): array
    {
        return [
            'id' => $interview->id,
            'type' => $this->normalize($interview->type),
            'scheduled_at' => $this->normalize($interview->scheduled_at),
            'outcome' => $this->normalize($interview->outcome),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        JobApplication $application,
        ?AuthUser $actor,
        ApplicationActivityType $type,
        array $metadata = [],
        ?string $comment = null,
        ?CarbonInterface $occurredAt = null,
    ): ApplicationActivity {
        return $application->activities()->create([
            'actor_auth_user_id' => $actor?->getKey(),
            'type' => $type,
            'comment' => $comment,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $changes[$field] = ['from' => $before[$field], 'to' => $value];
            }
        }

        return $changes;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return property_exists($value, 'value') ? $value->value : $value->name;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return $value;
    }
}
