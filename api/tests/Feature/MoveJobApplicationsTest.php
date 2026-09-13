<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Company;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveJobApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_reorder_an_application_within_its_column(): void
    {
        [$owner, $company] = $this->accountAndCompany();
        $first = $this->application($company, 'saved', 1, 'First');
        $second = $this->application($company, 'saved', 2, 'Second');
        $third = $this->application($company, 'saved', 3, 'Third');

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$third->id}/move", [
                'status' => 'saved',
                'target_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'saved')
            ->assertJsonPath('data.board_order', 1);

        $this->assertOrder([$third->id, $first->id, $second->id], 'saved');
    }

    public function test_owner_can_move_an_application_to_an_exact_position_in_another_column(): void
    {
        [$owner, $company] = $this->accountAndCompany();
        $saved = $this->application($company, 'saved', 1, 'Saved');
        $firstApplied = $this->application($company, 'applied', 1, 'First applied');
        $secondApplied = $this->application($company, 'applied', 2, 'Second applied');

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$saved->id}/move", [
                'status' => 'applied',
                'target_index' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.board_order', 2);

        $this->assertOrder([], 'saved');
        $this->assertOrder([$firstApplied->id, $saved->id, $secondApplied->id], 'applied');
    }

    public function test_target_index_is_validated_and_clamped_to_the_end(): void
    {
        [$owner, $company] = $this->accountAndCompany();
        $application = $this->application($company, 'saved', 1, 'Saved');
        $existing = $this->application($company, 'offer', 1, 'Existing offer');

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$application->id}/move", [
                'status' => 'unknown',
                'target_index' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status', 'target_index']]);

        $this->patchJson("/api/v1/job-applications/{$application->id}/move", [
            'status' => 'offer',
            'target_index' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('data.board_order', 2);

        $this->assertOrder([$existing->id, $application->id], 'offer');
    }

    public function test_other_accounts_application_is_hidden_from_move_endpoint(): void
    {
        [$owner] = $this->accountAndCompany();
        $otherApplication = JobApplication::factory()
            ->for(Company::factory()->for(AuthUser::factory()))
            ->create(['status' => 'saved', 'board_order' => 1]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$otherApplication->id}/move", [
                'status' => 'applied',
                'target_index' => 0,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('job_applications', [
            'id' => $otherApplication->id,
            'status' => 'saved',
            'board_order' => 1,
        ]);
    }

    public function test_create_status_update_and_delete_keep_board_positions_consistent(): void
    {
        [$owner, $company] = $this->accountAndCompany();
        $firstSaved = $this->application($company, 'saved', 1, 'First saved');
        $secondSaved = $this->application($company, 'saved', 2, 'Second saved');
        $applied = $this->application($company, 'applied', 1, 'Applied');

        $this->actingAs($owner)
            ->patchJson("/api/v1/job-applications/{$secondSaved->id}", ['status' => 'applied'])
            ->assertOk()
            ->assertJsonPath('data.board_order', 2);

        $this->assertOrder([$firstSaved->id], 'saved');
        $this->assertOrder([$applied->id, $secondSaved->id], 'applied');

        $this->deleteJson("/api/v1/job-applications/{$applied->id}")->assertOk();
        $this->assertOrder([$secondSaved->id], 'applied');

        $createdId = $this->postJson('/api/v1/job-applications', [
            'company_id' => $company->id,
            'position' => 'New saved',
            'status' => 'saved',
            'work_mode' => 'remote',
        ])
            ->assertCreated()
            ->assertJsonPath('data.board_order', 2)
            ->json('data.id');

        $this->assertOrder([$firstSaved->id, $createdId], 'saved');
    }

    public function test_guests_cannot_move_applications(): void
    {
        $application = JobApplication::factory()->create();

        $this->patchJson("/api/v1/job-applications/{$application->id}/move", [
            'status' => 'applied',
            'target_index' => 0,
        ])->assertUnauthorized();
    }

    /**
     * @return array{AuthUser, Company}
     */
    private function accountAndCompany(): array
    {
        $owner = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create();

        return [$owner, $company];
    }

    private function application(
        Company $company,
        string $status,
        int $boardOrder,
        string $position,
    ): JobApplication {
        return JobApplication::factory()->for($company)->create([
            'position' => $position,
            'status' => $status,
            'board_order' => $boardOrder,
        ]);
    }

    /**
     * @param  array<int, int>  $expectedIds
     */
    private function assertOrder(array $expectedIds, string $status): void
    {
        $actual = JobApplication::query()
            ->where('status', $status)
            ->orderBy('board_order')
            ->pluck('id')
            ->all();

        $this->assertSame($expectedIds, $actual);
        $this->assertSame(
            $expectedIds === [] ? [] : range(1, count($expectedIds)),
            JobApplication::query()
                ->where('status', $status)
                ->orderBy('board_order')
                ->pluck('board_order')
                ->all(),
        );
    }
}
