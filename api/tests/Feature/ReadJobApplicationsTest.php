<?php

namespace Tests\Feature;

use App\Models\AuthUser;
use App\Models\Company;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadJobApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_job_applications(): void
    {
        $application = JobApplication::factory()->create();

        $this->getJson('/api/v1/job-applications')->assertUnauthorized();
        $this->getJson("/api/v1/job-applications/{$application->id}")->assertUnauthorized();
    }

    public function test_applications_are_paginated_and_limited_to_the_owner(): void
    {
        $owner = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create();
        $otherCompany = Company::factory()->for($otherUser)->create();

        JobApplication::factory()->count(3)->for($company)->create();
        $otherApplication = JobApplication::factory()->for($otherCompany)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/job-applications?per_page=2&page=1');

        $response
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertNotContains($otherApplication->id, $response->json('data.*.id'));
    }

    public function test_applications_can_be_searched_by_position_or_company_name(): void
    {
        $owner = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();
        $northstar = Company::factory()->for($owner)->create(['name' => 'Northstar Labs']);
        $acme = Company::factory()->for($owner)->create(['name' => 'Acme']);
        $privateNorthstar = Company::factory()->for($otherUser)->create(['name' => 'Northstar Private']);

        $companyMatch = JobApplication::factory()->for($northstar)->create(['position' => 'Backend Developer']);
        $positionMatch = JobApplication::factory()->for($acme)->create(['position' => 'Laravel Engineer']);
        JobApplication::factory()->for($privateNorthstar)->create(['position' => 'Private Role']);

        $this->actingAs($owner)
            ->getJson('/api/v1/job-applications?search=northstar')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $companyMatch->id);

        $this->getJson('/api/v1/job-applications?search=laravel')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $positionMatch->id);
    }

    public function test_applications_can_be_filtered_by_status_work_mode_and_company(): void
    {
        $owner = AuthUser::factory()->create();
        $firstCompany = Company::factory()->for($owner)->create();
        $secondCompany = Company::factory()->for($owner)->create();

        $match = JobApplication::factory()->for($firstCompany)->create([
            'status' => 'interview',
            'work_mode' => 'remote',
        ]);
        JobApplication::factory()->for($firstCompany)->create([
            'status' => 'applied',
            'work_mode' => 'remote',
        ]);
        JobApplication::factory()->for($secondCompany)->create([
            'status' => 'interview',
            'work_mode' => 'remote',
        ]);

        $this->actingAs($owner)
            ->getJson(
                "/api/v1/job-applications?status=interview&work_mode=remote&company_id={$firstCompany->id}",
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_applications_support_only_whitelisted_sorting(): void
    {
        $owner = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create();
        $zebra = JobApplication::factory()->for($company)->create(['position' => 'Zebra Engineer']);
        $alpha = JobApplication::factory()->for($company)->create(['position' => 'Alpha Engineer']);

        $this->actingAs($owner)
            ->getJson('/api/v1/job-applications?sort=position&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $alpha->id)
            ->assertJsonPath('data.1.id', $zebra->id);

        $zebra->update(['board_order' => 1]);
        $alpha->update(['board_order' => 2]);

        $this->getJson('/api/v1/job-applications?sort=board_order&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $zebra->id)
            ->assertJsonPath('data.1.id', $alpha->id);

        $this->getJson('/api/v1/job-applications?sort=company.name&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonPath('errorCode', 'VALIDATION_ERROR')
            ->assertJsonCount(2, 'details');
    }

    public function test_application_filters_are_validated(): void
    {
        $owner = AuthUser::factory()->create();

        $this->actingAs($owner)
            ->getJson(
                '/api/v1/job-applications?page=0&per_page=101&status=unknown&work_mode=space&company_id=0',
            )
            ->assertUnprocessable()
            ->assertJsonPath('errorCode', 'VALIDATION_ERROR')
            ->assertJsonCount(5, 'details');
    }

    public function test_owner_can_read_an_application_with_its_company_summary(): void
    {
        $owner = AuthUser::factory()->create();
        $company = Company::factory()->for($owner)->create(['name' => 'Acme']);
        $application = JobApplication::factory()->for($company)->create([
            'position' => 'Full-stack Developer',
            'status' => 'offer',
            'work_mode' => 'hybrid',
            'employment_type' => 'full-time',
            'source_url' => 'https://acme.example.com/jobs/full-stack',
            'applied_at' => '2026-08-15',
            'next_action_at' => '2026-09-18 12:00:00',
            'salary_min' => 65_000.50,
            'salary_max' => 78_000.75,
            'currency' => 'EUR',
            'notes' => 'Review the offer.',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/job-applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.company.name', 'Acme')
            ->assertJsonPath('data.position', 'Full-stack Developer')
            ->assertJsonPath('data.status', 'offer')
            ->assertJsonPath('data.work_mode', 'hybrid')
            ->assertJsonPath('data.applied_at', '2026-08-15')
            ->assertJsonPath('data.salary_min', 65_000.50)
            ->assertJsonPath('data.salary_max', 78_000.75)
            ->assertJsonStructure(['data' => ['next_action_at', 'created_at', 'updated_at']]);
    }

    public function test_other_accounts_application_is_hidden_even_from_admin(): void
    {
        $recordOwner = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();
        $admin = AuthUser::factory()->admin()->create();
        $application = JobApplication::factory()
            ->for(Company::factory()->for($recordOwner))
            ->create();

        foreach ([$otherUser, $admin] as $actor) {
            $this->actingAs($actor)
                ->getJson("/api/v1/job-applications/{$application->id}")
                ->assertNotFound()
                ->assertJsonPath('errorCode', 'NOT_FOUND');
        }
    }

    public function test_empty_result_keeps_the_laravel_pagination_shape(): void
    {
        $owner = AuthUser::factory()->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/job-applications?search=missing')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
