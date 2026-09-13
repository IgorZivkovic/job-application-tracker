<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuthUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_database_seeder_creates_demo_users_and_auth_accounts(): void
    {
        $this->artisan('migrate:fresh --seed')->assertSuccessful();

        $this->assertDatabaseCount('users', 12);
        $this->assertDatabaseCount('auth_users', 2);
        $this->assertDatabaseCount('companies', 15);
        $this->assertDatabaseCount('job_applications', 26);
        $this->assertDatabaseCount('interviews', 12);
        $this->assertDatabaseCount('application_activities', 38);

        $admin = AuthUser::query()->where('email', 'admin@example.com')->firstOrFail();
        $viewer = AuthUser::query()->where('email', 'viewer@example.com')->firstOrFail();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame(Role::User, $viewer->role);
        $this->assertTrue(Hash::check('admin12345', $admin->password));
        $this->assertTrue(Hash::check('viewer12345', $viewer->password));

        $admin->load('companies.jobApplications.interviews');
        $viewer->load('companies.jobApplications.interviews');

        $adminApplications = $admin->companies->flatMap->jobApplications;
        $viewerApplications = $viewer->companies->flatMap->jobApplications;

        $this->assertCount(12, $admin->companies);
        $this->assertCount(15, $adminApplications);
        $this->assertCount(9, $adminApplications->flatMap->interviews);
        $this->assertCount(3, $viewer->companies);
        $this->assertCount(11, $viewerApplications);
        $this->assertCount(3, $viewerApplications->flatMap->interviews);
    }

    public function test_demo_users_are_repeatable_after_a_fresh_migration(): void
    {
        $this->artisan('migrate:fresh --seed')->assertSuccessful();
        $firstRun = $this->demoUsers();

        $this->artisan('migrate:fresh --seed')->assertSuccessful();

        $this->assertSame($firstRun, $this->demoUsers());
    }

    public function test_job_tracker_demo_data_is_repeatable_after_a_fresh_migration(): void
    {
        $this->artisan('migrate:fresh --seed')->assertSuccessful();
        $firstRun = $this->jobTrackerData();

        $this->artisan('migrate:fresh --seed')->assertSuccessful();

        $this->assertSame($firstRun, $this->jobTrackerData());
    }

    public function test_job_tracker_seeder_can_be_rerun_without_duplicate_demo_data(): void
    {
        $this->artisan('migrate:fresh --seed')->assertSuccessful();
        $firstRun = $this->jobTrackerData();

        $this->artisan('db:seed --class=JobTrackerSeeder')->assertSuccessful();

        $this->assertSame($firstRun, $this->jobTrackerData());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoUsers(): array
    {
        return DB::table('users')
            ->orderBy('id')
            ->get()
            ->map(fn (object $user) => (array) $user)
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function jobTrackerData(): array
    {
        return [
            'companies' => $this->rowsWithoutTimestamps('companies'),
            'job_applications' => $this->rowsWithoutTimestamps('job_applications'),
            'interviews' => $this->rowsWithoutTimestamps('interviews'),
            'application_activities' => $this->rowsWithoutTimestamps('application_activities'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsWithoutTimestamps(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(function (object $row): array {
                $attributes = (array) $row;
                unset($attributes['created_at'], $attributes['updated_at']);

                return $attributes;
            })
            ->all();
    }
}
