<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobTrackerDatabaseSchemaTest extends TestCase
{
    public function test_migrations_create_the_job_tracker_schema(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();

        $this->assertTrue(Schema::hasColumns('companies', [
            'id',
            'auth_user_id',
            'name',
            'website',
            'location',
            'notes',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('job_applications', [
            'id',
            'company_id',
            'position',
            'status',
            'board_order',
            'work_mode',
            'employment_type',
            'source_url',
            'applied_at',
            'next_action_at',
            'salary_min',
            'salary_max',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('interviews', [
            'id',
            'job_application_id',
            'type',
            'scheduled_at',
            'contact_name',
            'contact_email',
            'location_or_link',
            'notes',
            'outcome',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('application_activities', [
            'id',
            'job_application_id',
            'actor_auth_user_id',
            'type',
            'comment',
            'metadata',
            'occurred_at',
        ]));
    }

    public function test_company_names_are_unique_per_account(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $firstAccountId = $this->insertAuthUser('first@example.com');
        $secondAccountId = $this->insertAuthUser('second@example.com');

        $this->insertCompany($firstAccountId, 'Acme');
        $this->insertCompany($secondAccountId, 'Acme');

        $this->expectException(QueryException::class);
        $this->insertCompany($firstAccountId, 'Acme');
    }

    public function test_company_with_applications_cannot_be_deleted(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        ['company_id' => $companyId] = $this->insertApplicationFixture();

        $this->expectException(QueryException::class);
        DB::table('companies')->where('id', $companyId)->delete();
    }

    public function test_deleting_an_application_also_deletes_its_interviews(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        ['application_id' => $applicationId] = $this->insertApplicationFixture();

        $interviewId = DB::table('interviews')->insertGetId([
            'job_application_id' => $applicationId,
            'type' => 'technical',
            'scheduled_at' => '2026-09-15 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('job_applications')->where('id', $applicationId)->delete();

        $this->assertFalse(DB::table('interviews')->where('id', $interviewId)->exists());
    }

    public function test_job_tracker_migrations_can_be_rolled_back_and_run_again(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->artisan('migrate:rollback')->assertSuccessful();

        $this->assertFalse(Schema::hasTable('companies'));
        $this->assertFalse(Schema::hasTable('job_applications'));
        $this->assertFalse(Schema::hasTable('interviews'));
        $this->assertFalse(Schema::hasTable('application_activities'));

        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('companies'));
        $this->assertTrue(Schema::hasTable('job_applications'));
        $this->assertTrue(Schema::hasTable('interviews'));
        $this->assertTrue(Schema::hasTable('application_activities'));
    }

    private function insertApplicationFixture(): array
    {
        $accountId = $this->insertAuthUser('owner@example.com');
        $companyId = $this->insertCompany($accountId, 'Acme');
        $applicationId = DB::table('job_applications')->insertGetId([
            'company_id' => $companyId,
            'position' => 'Backend Developer',
            'status' => 'applied',
            'work_mode' => 'hybrid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'company_id' => $companyId,
            'application_id' => $applicationId,
        ];
    }

    private function insertAuthUser(string $email): int
    {
        return DB::table('auth_users')->insertGetId([
            'email' => $email,
            'password' => 'hashed-password',
            'role' => 'user',
        ]);
    }

    private function insertCompany(int $accountId, string $name): int
    {
        return DB::table('companies')->insertGetId([
            'auth_user_id' => $accountId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
