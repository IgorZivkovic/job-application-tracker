<?php

namespace Tests\Feature;

use App\Enums\ApplicationActivityType;
use App\Models\ApplicationActivity;
use App\Models\AuthUser;
use App\Models\Company;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Services\ApplicationActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ApplicationActivityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_changes_are_recorded_and_listed_newest_first(): void
    {
        $owner = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create(['name' => 'Acme']);

        $createResponse = $this->actingAs($owner)->postJson('/api/v1/job-applications', [
            'company_id' => $company->id,
            'position' => 'Angular Developer',
            'status' => 'saved',
            'work_mode' => 'remote',
        ])->assertCreated();

        $applicationId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/job-applications/{$applicationId}", [
            'position' => 'Senior Angular Developer',
            'status' => 'applied',
        ])->assertOk();

        $this->getJson("/api/v1/job-applications/{$applicationId}/activities?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', ApplicationActivityType::ApplicationUpdated->value)
            ->assertJsonPath('data.0.metadata.changes.position.from', 'Angular Developer')
            ->assertJsonPath('data.0.metadata.changes.position.to', 'Senior Angular Developer')
            ->assertJsonPath('data.1.type', ApplicationActivityType::StatusChanged->value)
            ->assertJsonPath('data.1.metadata.from_status', 'saved')
            ->assertJsonPath('data.1.metadata.to_status', 'applied')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.actor.email', $owner->email);
    }

    public function test_no_op_update_and_same_column_reorder_do_not_add_activity(): void
    {
        $owner = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create();
        $application = JobApplication::factory()->for($company)->create([
            'position' => 'Frontend Developer',
            'status' => 'applied',
            'board_order' => 1,
        ]);
        JobApplication::factory()->for($company)->create([
            'status' => 'applied',
            'board_order' => 2,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$application->id}", [
                'position' => 'Frontend Developer',
            ])
            ->assertOk();

        $this->patchJson("/api/v1/job-applications/{$application->id}/move", [
            'status' => 'applied',
            'target_index' => 1,
        ])->assertOk();

        $this->assertDatabaseCount('application_activities', 0);
    }

    public function test_cross_column_move_records_status_change(): void
    {
        $owner = AuthUser::factory()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($owner))
            ->create(['status' => 'saved']);

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$application->id}/move", [
                'status' => 'interview',
                'target_index' => 0,
            ])
            ->assertOk();

        $this->assertDatabaseHas('application_activities', [
            'job_application_id' => $application->id,
            'actor_auth_user_id' => $owner->id,
            'type' => ApplicationActivityType::StatusChanged->value,
        ]);
    }

    public function test_interview_lifecycle_is_recorded(): void
    {
        $owner = AuthUser::factory()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($owner))
            ->create();
        $baseUrl = "/api/v1/job-applications/{$application->id}/interviews";

        $interviewId = $this->actingAs($owner)->postJson($baseUrl, [
            'type' => 'technical',
            'scheduled_at' => '2026-09-20T10:30:00+02:00',
            'outcome' => null,
        ])->assertCreated()->json('data.id');

        $this->patchJson("{$baseUrl}/{$interviewId}", [
            'scheduled_at' => '2026-09-22T10:30:00+02:00',
            'outcome' => 'passed',
        ])->assertOk();

        $this->deleteJson("{$baseUrl}/{$interviewId}")->assertOk();

        $types = ApplicationActivity::query()
            ->where('job_application_id', $application->id)
            ->orderBy('id')
            ->pluck('type')
            ->map(fn (ApplicationActivityType $type): string => $type->value)
            ->all();

        $this->assertSame([
            'interview_scheduled',
            'interview_rescheduled',
            'interview_outcome_recorded',
            'interview_deleted',
        ], $types);
    }

    public function test_owner_can_add_a_trimmed_comment_but_cannot_spoof_server_fields(): void
    {
        $owner = AuthUser::factory()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($owner))
            ->create();
        $url = "/api/v1/job-applications/{$application->id}/activities";

        $this->actingAs($owner)
            ->postJson($url, ['comment' => '  Followed up with the recruiter.  '])
            ->assertCreated()
            ->assertJsonPath('data.type', 'comment_added')
            ->assertJsonPath('data.comment', 'Followed up with the recruiter.')
            ->assertJsonPath('data.actor.id', $owner->id);

        $this->postJson($url, [
            'comment' => 'Spoofed event',
            'type' => 'status_changed',
            'actor_auth_user_id' => 999,
            'occurred_at' => '2020-01-01T00:00:00Z',
            'metadata' => ['to_status' => 'offer'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errorCode', 'VALIDATION_ERROR');

        $this->postJson($url, ['comment' => '   '])
            ->assertUnprocessable()
            ->assertJsonPath('errorCode', 'VALIDATION_ERROR');
    }

    public function test_other_accounts_timeline_is_hidden_and_activities_cascade_on_delete(): void
    {
        $owner = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($owner))
            ->create();
        $activity = ApplicationActivity::query()->create([
            'job_application_id' => $application->id,
            'actor_auth_user_id' => $owner->id,
            'type' => 'comment_added',
            'comment' => 'Private note',
            'occurred_at' => now(),
        ]);
        $url = "/api/v1/job-applications/{$application->id}/activities";

        $this->actingAs($otherUser)->getJson($url)->assertNotFound();
        $this->postJson($url, ['comment' => 'Blocked'])->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson("/api/v1/job-applications/{$application->id}")
            ->assertOk();

        $this->assertDatabaseMissing('application_activities', ['id' => $activity->id]);
    }

    public function test_application_change_is_rolled_back_when_activity_recording_fails(): void
    {
        $owner = AuthUser::factory()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($owner))
            ->create(['position' => 'Original position']);

        $this->partialMock(
            ApplicationActivityRecorder::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('recordApplicationChanges')
                    ->once()
                    ->andThrow(new RuntimeException('Activity storage unavailable.'));
            },
        );

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$application->id}", [
                'position' => 'Should be rolled back',
            ])
            ->assertServerError();

        $this->assertDatabaseHas('job_applications', [
            'id' => $application->id,
            'position' => 'Original position',
        ]);
        $this->assertDatabaseCount('application_activities', 0);
    }
}
