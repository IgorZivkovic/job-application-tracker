<?php

namespace Tests\Feature;

use App\Enums\InterviewOutcome;
use App\Enums\InterviewType;
use App\Enums\ApplicationActivityType;
use App\Models\ApplicationActivity;
use App\Enums\JobApplicationStatus;
use App\Enums\WorkMode;
use App\Models\AuthUser;
use App\Models\Company;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTrackerDomainModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_expose_the_job_tracker_relationships(): void
    {
        $account = AuthUser::factory()->create();
        $company = Company::factory()->for($account)->create();
        $application = JobApplication::factory()->for($company)->create();
        $interview = Interview::factory()->for($application)->create();
        $activity = $application->activities()->create([
            'actor_auth_user_id' => $account->id,
            'type' => 'comment_added',
            'comment' => 'Followed up.',
            'metadata' => ['channel' => 'email'],
            'occurred_at' => '2026-09-13 10:00:00',
        ]);

        $this->assertTrue($account->companies->firstOrFail()->is($company));
        $this->assertTrue($company->authUser->is($account));
        $this->assertTrue($company->jobApplications->firstOrFail()->is($application));
        $this->assertTrue($application->company->is($company));
        $this->assertTrue($application->interviews->firstOrFail()->is($interview));
        $this->assertTrue($interview->jobApplication->is($application));
        $this->assertTrue($application->activities->firstOrFail()->is($activity));
        $this->assertTrue($activity->jobApplication->is($application));
        $this->assertTrue($activity->actor->is($account));
        $this->assertTrue($account->applicationActivities->firstOrFail()->is($activity));
        $this->assertSame(ApplicationActivityType::CommentAdded, $activity->type);
        $this->assertSame(['channel' => 'email'], $activity->metadata);
        $this->assertInstanceOf(ApplicationActivity::class, $activity);
    }

    public function test_job_application_casts_domain_values(): void
    {
        $application = JobApplication::factory()->create([
            'status' => 'interview',
            'work_mode' => 'hybrid',
            'applied_at' => '2026-09-01',
            'next_action_at' => '2026-09-15 10:30:00',
            'salary_min' => 65_000,
            'salary_max' => 78_000,
        ]);

        $this->assertSame(JobApplicationStatus::Interview, $application->status);
        $this->assertSame(WorkMode::Hybrid, $application->work_mode);
        $this->assertSame('2026-09-01', $application->applied_at->format('Y-m-d'));
        $this->assertSame('2026-09-15 10:30:00', $application->next_action_at->format('Y-m-d H:i:s'));
        $this->assertSame('65000.00', $application->salary_min);
        $this->assertSame('78000.00', $application->salary_max);
    }

    public function test_interview_casts_type_schedule_and_optional_outcome(): void
    {
        $completed = Interview::factory()->create([
            'type' => 'technical',
            'scheduled_at' => '2026-09-15 10:30:00',
            'outcome' => 'passed',
        ]);
        $upcoming = Interview::factory()->create([
            'type' => 'hr',
            'scheduled_at' => '2026-09-20 14:00:00',
            'outcome' => null,
        ]);

        $this->assertSame(InterviewType::Technical, $completed->type);
        $this->assertSame('2026-09-15 10:30:00', $completed->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(InterviewOutcome::Passed, $completed->outcome);
        $this->assertSame(InterviewType::Hr, $upcoming->type);
        $this->assertNull($upcoming->outcome);
    }
}
