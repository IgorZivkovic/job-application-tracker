<?php

namespace Database\Seeders;

use App\Enums\ApplicationActivityType;
use App\Enums\InterviewOutcome;
use App\Enums\InterviewType;
use App\Enums\JobApplicationStatus;
use App\Enums\WorkMode;
use App\Models\AuthUser;
use App\Models\JobApplication;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class JobTrackerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $accounts = AuthUser::query()
            ->whereIn('email', array_keys($this->demoData()))
            ->get()
            ->keyBy('email');

        foreach ($this->demoData() as $email => $companies) {
            $account = $accounts->get($email)
                ?? throw new RuntimeException("Demo account {$email} must exist before seeding job tracker data.");

            foreach ($companies as $companyData) {
                $applications = $companyData['applications'];
                unset($companyData['applications']);

                $company = $account->companies()->firstOrCreate(
                    ['name' => $companyData['name']],
                    $companyData,
                );

                foreach ($applications as $applicationData) {
                    $interviews = $applicationData['interviews'] ?? [];
                    unset($applicationData['interviews']);

                    $application = $company->jobApplications()->firstOrCreate(
                        ['position' => $applicationData['position']],
                        $applicationData,
                    );

                    if (! $application->activities()->exists()) {
                        $type = $application->wasRecentlyCreated
                            ? ApplicationActivityType::ApplicationCreated
                            : ApplicationActivityType::TrackingStarted;

                        $application->activities()->create([
                            'actor_auth_user_id' => null,
                            'type' => $type,
                            'comment' => null,
                            'metadata' => $type === ApplicationActivityType::ApplicationCreated ? [
                                'application' => [
                                    'company' => ['id' => $company->id, 'name' => $company->name],
                                    'position' => $application->position,
                                    'status' => $application->applied_at === null
                                        ? JobApplicationStatus::Saved->value
                                        : JobApplicationStatus::Applied->value,
                                ],
                            ] : null,
                            'occurred_at' => $application->applied_at
                                ?? now()->setDate(2026, 9, 1)->startOfDay()->addMinutes($application->id),
                        ]);
                    }

                    foreach ($interviews as $interviewData) {
                        $interview = $application->interviews()->firstOrCreate(
                            [
                                'type' => $interviewData['type'],
                                'scheduled_at' => $interviewData['scheduled_at'],
                            ],
                            $interviewData,
                        );

                        $application->activities()->firstOrCreate(
                            [
                                'type' => ApplicationActivityType::InterviewScheduled,
                                'occurred_at' => $interview->scheduled_at->copy()->subWeek(),
                            ],
                            [
                                'actor_auth_user_id' => null,
                                'comment' => null,
                                'metadata' => [
                                    'interview' => [
                                        'id' => $interview->id,
                                        'type' => $interview->type->value,
                                        'scheduled_at' => $interview->scheduled_at->toISOString(),
                                        'outcome' => $interview->outcome?->value,
                                    ],
                                ],
                            ],
                        );
                    }

                    $this->seedDemoActivities($application, $account, $company->name);
                }
            }

            $this->normalizeBoardOrder($account);
        }
    }

    private function seedDemoActivities(
        JobApplication $application,
        AuthUser $account,
        string $companyName,
    ): void {
        $activityData = $this->demoActivities()["{$companyName}|{$application->position}"] ?? [];

        foreach ($activityData as $activity) {
            $metadata = $activity['metadata'] ?? null;
            $interviewType = $activity['interview_type'] ?? null;

            if ($interviewType !== null) {
                $interview = $application->interviews()
                    ->where('type', $interviewType)
                    ->firstOrFail();

                $metadata = [
                    'interview_id' => $interview->id,
                    'interview_type' => $interview->type->value,
                    ...$metadata,
                ];
            }

            $application->activities()->firstOrCreate(
                [
                    'type' => $activity['type'],
                    'occurred_at' => $activity['occurred_at'],
                ],
                [
                    'actor_auth_user_id' => $account->id,
                    'comment' => $activity['comment'] ?? null,
                    'metadata' => $metadata,
                ],
            );
        }
    }

    /**
     * Deterministic activity histories keep demo data repeatable while making
     * the timeline useful during development and in project screenshots.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function demoActivities(): array
    {
        return [
            'Ember Commerce|Senior Full-stack Engineer' => [
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Recruiter confirmed that the team is reviewing my application this week.',
                    'occurred_at' => '2026-08-03 09:20:00',
                ],
                [
                    'type' => ApplicationActivityType::InterviewOutcomeRecorded,
                    'interview_type' => InterviewType::Technical,
                    'metadata' => ['from_outcome' => null, 'to_outcome' => InterviewOutcome::Passed->value],
                    'occurred_at' => '2026-08-12 15:10:00',
                ],
                [
                    'type' => ApplicationActivityType::StatusChanged,
                    'metadata' => [
                        'from_status' => JobApplicationStatus::Applied->value,
                        'to_status' => JobApplicationStatus::Interview->value,
                    ],
                    'occurred_at' => '2026-08-13 10:15:00',
                ],
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Sent the requested architecture sample before the final interview.',
                    'occurred_at' => '2026-08-21 11:40:00',
                ],
                [
                    'type' => ApplicationActivityType::InterviewOutcomeRecorded,
                    'interview_type' => InterviewType::Final,
                    'metadata' => ['from_outcome' => null, 'to_outcome' => InterviewOutcome::Passed->value],
                    'occurred_at' => '2026-08-26 17:05:00',
                ],
                [
                    'type' => ApplicationActivityType::StatusChanged,
                    'metadata' => [
                        'from_status' => JobApplicationStatus::Interview->value,
                        'to_status' => JobApplicationStatus::Offer->value,
                    ],
                    'occurred_at' => '2026-08-27 09:30:00',
                ],
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Offer received. Comparing benefits and preparing questions about the on-call rotation.',
                    'occurred_at' => '2026-09-02 14:25:00',
                ],
            ],
            'Harbor Financial|Web Application Engineer' => [
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Recruiter shared the interview agenda and the names of the two engineers joining the call.',
                    'occurred_at' => '2026-09-02 16:15:00',
                ],
                [
                    'type' => ApplicationActivityType::InterviewOutcomeRecorded,
                    'interview_type' => InterviewType::Screening,
                    'metadata' => ['from_outcome' => null, 'to_outcome' => InterviewOutcome::Passed->value],
                    'occurred_at' => '2026-09-04 10:20:00',
                ],
                [
                    'type' => ApplicationActivityType::StatusChanged,
                    'metadata' => [
                        'from_status' => JobApplicationStatus::Applied->value,
                        'to_status' => JobApplicationStatus::Interview->value,
                    ],
                    'occurred_at' => '2026-09-04 10:30:00',
                ],
            ],
            'BrightPeak Software|Angular Platform Engineer' => [
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Sent a short follow-up with links to the design-system and migration case studies.',
                    'occurred_at' => '2026-09-12 09:10:00',
                ],
            ],
            'Northstar Labs|Senior Angular Developer' => [
                [
                    'type' => ApplicationActivityType::CommentAdded,
                    'comment' => 'Follow up on Friday if there is no update from the hiring team.',
                    'occurred_at' => '2026-09-11 13:45:00',
                ],
            ],
        ];
    }

    private function normalizeBoardOrder(AuthUser $account): void
    {
        foreach (JobApplicationStatus::cases() as $status) {
            JobApplication::query()
                ->ownedBy($account)
                ->where('status', $status)
                ->orderBy('board_order')
                ->orderBy('id')
                ->pluck('id')
                ->each(static function (int $id, int $index): void {
                    JobApplication::query()->whereKey($id)->update(['board_order' => $index + 1]);
                });
        }
    }

    private function demoData(): array
    {
        return [
            'admin@example.com' => [
                [
                    'name' => 'Northstar Labs',
                    'website' => 'https://northstar.example.com',
                    'location' => 'Berlin, Germany',
                    'notes' => 'Product company with a distributed engineering team.',
                    'applications' => [
                        [
                            'position' => 'Senior Angular Developer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://northstar.example.com/jobs/angular-developer',
                            'applied_at' => '2026-08-20',
                            'next_action_at' => '2026-09-16 09:00:00',
                            'salary_min' => 70_000,
                            'salary_max' => 85_000,
                            'currency' => 'EUR',
                            'notes' => 'Follow up with the recruiter after the initial review.',
                        ],
                    ],
                ],
                [
                    'name' => 'Vertex Systems',
                    'website' => 'https://vertex.example.com',
                    'location' => 'Budapest, Hungary',
                    'notes' => null,
                    'applications' => [
                        [
                            'position' => 'Laravel Developer',
                            'status' => JobApplicationStatus::Interview,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://vertex.example.com/careers/laravel-developer',
                            'applied_at' => '2026-08-10',
                            'next_action_at' => '2026-09-14 10:00:00',
                            'salary_min' => 65_000,
                            'salary_max' => 78_000,
                            'currency' => 'EUR',
                            'notes' => 'Prepare examples of API design and database optimization.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Screening,
                                    'scheduled_at' => '2026-08-18 11:00:00',
                                    'contact_name' => 'Marta Kovacs',
                                    'contact_email' => 'marta@vertex.example.com',
                                    'location_or_link' => 'https://meet.example.com/vertex-screening',
                                    'notes' => 'Introductory call with the recruiter.',
                                    'outcome' => InterviewOutcome::Passed,
                                ],
                                [
                                    'type' => InterviewType::Technical,
                                    'scheduled_at' => '2026-09-14 10:00:00',
                                    'contact_name' => 'Daniel Horvat',
                                    'contact_email' => 'daniel@vertex.example.com',
                                    'location_or_link' => 'https://meet.example.com/vertex-technical',
                                    'notes' => 'System design and pair-programming session.',
                                    'outcome' => null,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Blue Orbit',
                    'website' => null,
                    'location' => 'Remote',
                    'notes' => 'Early-stage SaaS company.',
                    'applications' => [
                        [
                            'position' => 'Full-stack Engineer',
                            'status' => JobApplicationStatus::Saved,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'contract',
                            'source_url' => 'https://jobs.example.com/blue-orbit-full-stack',
                            'notes' => 'Review the role before applying.',
                        ],
                        [
                            'position' => 'Frontend Lead',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-07-03',
                            'notes' => 'Good technical discussion, but the company chose an internal candidate.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Final,
                                    'scheduled_at' => '2026-07-21 14:00:00',
                                    'contact_name' => 'Elena Brooks',
                                    'contact_email' => 'elena@blue-orbit.example.com',
                                    'location_or_link' => 'https://meet.example.com/blue-orbit-final',
                                    'notes' => null,
                                    'outcome' => InterviewOutcome::Failed,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'BrightPeak Software',
                    'website' => 'https://brightpeak.example.com',
                    'location' => 'Prague, Czechia',
                    'notes' => 'B2B platform team modernizing a large frontend codebase.',
                    'applications' => [
                        [
                            'position' => 'Senior Frontend Engineer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://brightpeak.example.com/jobs/senior-frontend',
                            'applied_at' => '2026-06-18',
                            'salary_min' => 62_000,
                            'salary_max' => 75_000,
                            'currency' => 'EUR',
                            'notes' => 'Automated rejection after the application review.',
                        ],
                        [
                            'position' => 'Angular Platform Engineer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://brightpeak.example.com/jobs/angular-platform',
                            'applied_at' => '2026-09-05',
                            'next_action_at' => '2026-09-18 09:00:00',
                            'salary_min' => 68_000,
                            'salary_max' => 82_000,
                            'currency' => 'EUR',
                            'notes' => 'Send a short follow-up if there is no response after two weeks.',
                        ],
                    ],
                ],
                [
                    'name' => 'Greenline Mobility',
                    'website' => 'https://greenline.example.com',
                    'location' => 'Vienna, Austria',
                    'notes' => 'Mobility startup building tools for public transport operators.',
                    'applications' => [
                        [
                            'position' => 'Full-stack Developer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://greenline.example.com/careers/full-stack',
                            'applied_at' => '2026-07-02',
                            'notes' => 'Role was put on hold after the first recruiter conversation.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Screening,
                                    'scheduled_at' => '2026-07-09 10:30:00',
                                    'contact_name' => 'Lena Bauer',
                                    'contact_email' => 'lena@greenline.example.com',
                                    'location_or_link' => 'https://meet.example.com/greenline-screening',
                                    'notes' => 'Discussed product scope and remote-work expectations.',
                                    'outcome' => InterviewOutcome::Cancelled,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Harbor Financial',
                    'website' => 'https://harbor-financial.example.com',
                    'location' => 'Zagreb, Croatia',
                    'notes' => 'Fintech product company with a small web platform group.',
                    'applications' => [
                        [
                            'position' => 'Web Application Engineer',
                            'status' => JobApplicationStatus::Interview,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://harbor-financial.example.com/jobs/web-engineer',
                            'applied_at' => '2026-08-25',
                            'next_action_at' => '2026-09-19 13:00:00',
                            'salary_min' => 66_000,
                            'salary_max' => 80_000,
                            'currency' => 'EUR',
                            'notes' => 'Prepare an architecture walkthrough and testing examples.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Screening,
                                    'scheduled_at' => '2026-09-04 09:30:00',
                                    'contact_name' => 'Ivana Markovic',
                                    'contact_email' => 'ivana@harbor-financial.example.com',
                                    'location_or_link' => 'https://meet.example.com/harbor-screening',
                                    'notes' => 'Positive introductory call.',
                                    'outcome' => InterviewOutcome::Passed,
                                ],
                                [
                                    'type' => InterviewType::Technical,
                                    'scheduled_at' => '2026-09-19 13:00:00',
                                    'contact_name' => 'Mateo Kovac',
                                    'contact_email' => 'mateo@harbor-financial.example.com',
                                    'location_or_link' => 'https://meet.example.com/harbor-technical',
                                    'notes' => 'Frontend architecture, API integration, and pair programming.',
                                    'outcome' => null,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Cloud Harbor',
                    'website' => 'https://cloud-harbor.example.com',
                    'location' => 'Remote',
                    'notes' => 'Cloud consultancy working with distributed European teams.',
                    'applications' => [
                        [
                            'position' => 'Angular Consultant',
                            'status' => JobApplicationStatus::Saved,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'contract',
                            'source_url' => 'https://cloud-harbor.example.com/jobs/angular-consultant',
                            'next_action_at' => '2026-09-15 18:00:00',
                            'salary_min' => 400,
                            'salary_max' => 480,
                            'currency' => 'EUR',
                            'notes' => 'Daily-rate role. Tailor the CV before applying.',
                        ],
                    ],
                ],
                [
                    'name' => 'Meridian Health',
                    'website' => 'https://meridian-health.example.com',
                    'location' => 'Munich, Germany',
                    'notes' => 'Digital health company with a regulated product environment.',
                    'applications' => [
                        [
                            'position' => 'Frontend Platform Engineer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-05-22',
                            'notes' => 'No response after the technical assignment; closed after follow-up.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Technical,
                                    'scheduled_at' => '2026-06-05 14:00:00',
                                    'contact_name' => 'Jonas Weber',
                                    'contact_email' => 'jonas@meridian-health.example.com',
                                    'location_or_link' => 'https://meet.example.com/meridian-technical',
                                    'notes' => 'Take-home review and frontend system design.',
                                    'outcome' => InterviewOutcome::Failed,
                                ],
                            ],
                        ],
                        [
                            'position' => 'UI Infrastructure Engineer',
                            'status' => JobApplicationStatus::Withdrawn,
                            'work_mode' => WorkMode::Onsite,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-08-03',
                            'notes' => 'Withdrew after learning that relocation was required.',
                        ],
                    ],
                ],
                [
                    'name' => 'Ember Commerce',
                    'website' => 'https://ember-commerce.example.com',
                    'location' => 'Warsaw, Poland',
                    'notes' => 'Commerce platform serving independent European retailers.',
                    'applications' => [
                        [
                            'position' => 'Senior Full-stack Engineer',
                            'status' => JobApplicationStatus::Offer,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://ember-commerce.example.com/jobs/full-stack',
                            'applied_at' => '2026-07-28',
                            'next_action_at' => '2026-09-16 17:00:00',
                            'salary_min' => 74_000,
                            'salary_max' => 88_000,
                            'currency' => 'EUR',
                            'notes' => 'Review the offer, benefits, and on-call expectations.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Technical,
                                    'scheduled_at' => '2026-08-12 13:00:00',
                                    'contact_name' => 'Kasia Nowak',
                                    'contact_email' => 'kasia@ember-commerce.example.com',
                                    'location_or_link' => 'https://meet.example.com/ember-technical',
                                    'notes' => 'API design and Angular debugging exercise.',
                                    'outcome' => InterviewOutcome::Passed,
                                ],
                                [
                                    'type' => InterviewType::Final,
                                    'scheduled_at' => '2026-08-26 15:30:00',
                                    'contact_name' => 'Piotr Zielinski',
                                    'contact_email' => 'piotr@ember-commerce.example.com',
                                    'location_or_link' => 'https://meet.example.com/ember-final',
                                    'notes' => 'Final discussion with the engineering director.',
                                    'outcome' => InterviewOutcome::Passed,
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'NovaWorks',
                    'website' => 'https://novaworks.example.com',
                    'location' => 'Remote',
                    'notes' => 'Product studio delivering Laravel and Angular applications.',
                    'applications' => [
                        [
                            'position' => 'PHP and Angular Engineer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://novaworks.example.com/careers/php-angular',
                            'applied_at' => '2026-09-09',
                            'next_action_at' => '2026-09-23 10:00:00',
                            'salary_min' => 60_000,
                            'salary_max' => 72_000,
                            'currency' => 'EUR',
                            'notes' => 'Application sent through the company careers page.',
                        ],
                    ],
                ],
                [
                    'name' => 'Linear Works',
                    'website' => 'https://linear-works.example.com',
                    'location' => 'London, United Kingdom',
                    'notes' => null,
                    'applications' => [
                        [
                            'position' => 'TypeScript Engineer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-06-30',
                            'notes' => 'Position received a high number of applications.',
                        ],
                    ],
                ],
                [
                    'name' => 'Monolith Systems',
                    'website' => null,
                    'location' => 'Belgrade, Serbia',
                    'notes' => 'Enterprise software vendor migrating legacy internal products.',
                    'applications' => [
                        [
                            'position' => 'Software Engineer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Onsite,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-08-14',
                            'notes' => 'Rejected after compensation expectations did not align.',
                        ],
                    ],
                ],
            ],
            'viewer@example.com' => [
                [
                    'name' => 'Cedar Analytics',
                    'website' => 'https://cedar.example.com',
                    'location' => 'Amsterdam, Netherlands',
                    'notes' => null,
                    'applications' => [
                        [
                            'position' => 'UI Engineer',
                            'status' => JobApplicationStatus::Offer,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-07-15',
                            'next_action_at' => '2026-09-18 12:00:00',
                            'salary_min' => 72_000,
                            'salary_max' => 82_000,
                            'currency' => 'EUR',
                            'notes' => 'Offer review deadline is September 18.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Final,
                                    'scheduled_at' => '2026-08-28 13:30:00',
                                    'contact_name' => 'Sophie de Vries',
                                    'contact_email' => 'sophie@cedar.example.com',
                                    'location_or_link' => 'Cedar Analytics Amsterdam office',
                                    'notes' => 'Final conversation with the engineering manager.',
                                    'outcome' => InterviewOutcome::Passed,
                                ],
                            ],
                        ],
                        [
                            'position' => 'Data Visualization Engineer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://cedar.example.com/jobs/data-visualization',
                            'applied_at' => '2026-09-06',
                            'next_action_at' => '2026-09-20 10:00:00',
                            'salary_min' => 68_000,
                            'salary_max' => 79_000,
                            'currency' => 'EUR',
                            'notes' => 'Follow up after the portfolio review window closes.',
                        ],
                        [
                            'position' => 'Angular Consultant',
                            'status' => JobApplicationStatus::Saved,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'contract',
                            'source_url' => 'https://cedar.example.com/jobs/angular-consultant',
                            'notes' => 'Compare the daily rate with the current contract pipeline.',
                        ],
                        [
                            'position' => 'Frontend Architect',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-06-11',
                            'notes' => 'Role was filled before the technical interview stage.',
                        ],
                    ],
                ],
                [
                    'name' => 'Pixel Forge',
                    'website' => 'https://pixel-forge.example.com',
                    'location' => 'Remote',
                    'notes' => 'Design-focused product studio.',
                    'applications' => [
                        [
                            'position' => 'Frontend Developer',
                            'status' => JobApplicationStatus::Interview,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://pixel-forge.example.com/jobs/frontend',
                            'applied_at' => '2026-08-27',
                            'next_action_at' => '2026-09-17 15:00:00',
                            'notes' => 'Prepare a walkthrough of a recent Angular project.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Hr,
                                    'scheduled_at' => '2026-09-17 15:00:00',
                                    'contact_name' => 'Nina Patel',
                                    'contact_email' => 'nina@pixel-forge.example.com',
                                    'location_or_link' => 'https://meet.example.com/pixel-forge-hr',
                                    'notes' => null,
                                    'outcome' => null,
                                ],
                            ],
                        ],
                        [
                            'position' => 'Design Systems Engineer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'full-time',
                            'source_url' => 'https://pixel-forge.example.com/jobs/design-systems',
                            'applied_at' => '2026-09-02',
                            'next_action_at' => '2026-09-16 16:00:00',
                            'notes' => 'Shared component architecture is central to the role.',
                        ],
                        [
                            'position' => 'Accessibility Engineer',
                            'status' => JobApplicationStatus::Saved,
                            'work_mode' => WorkMode::Remote,
                            'employment_type' => 'part-time',
                            'source_url' => 'https://pixel-forge.example.com/jobs/accessibility',
                            'notes' => 'Review WCAG audit examples before tailoring the application.',
                        ],
                        [
                            'position' => 'TypeScript UI Developer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Onsite,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-07-19',
                            'notes' => 'The team selected a candidate already based in the office city.',
                        ],
                    ],
                ],
                [
                    'name' => 'Atlas Commerce',
                    'website' => null,
                    'location' => 'Vienna, Austria',
                    'notes' => null,
                    'applications' => [
                        [
                            'position' => 'PHP Developer',
                            'status' => JobApplicationStatus::Withdrawn,
                            'work_mode' => WorkMode::Onsite,
                            'employment_type' => 'contract',
                            'applied_at' => '2026-08-01',
                            'notes' => 'Role changed to fully onsite after applying.',
                            'interviews' => [
                                [
                                    'type' => InterviewType::Screening,
                                    'scheduled_at' => '2026-08-12 09:30:00',
                                    'contact_name' => 'Markus Steiner',
                                    'contact_email' => 'markus@atlas.example.com',
                                    'location_or_link' => 'https://meet.example.com/atlas-screening',
                                    'notes' => 'Cancelled after the work-mode change.',
                                    'outcome' => InterviewOutcome::Cancelled,
                                ],
                            ],
                        ],
                        [
                            'position' => 'Backend Integration Engineer',
                            'status' => JobApplicationStatus::Applied,
                            'work_mode' => WorkMode::Hybrid,
                            'employment_type' => 'contract',
                            'source_url' => 'https://jobs.example.com/atlas-integrations',
                            'applied_at' => '2026-09-08',
                            'next_action_at' => '2026-09-22 09:30:00',
                            'notes' => 'Application emphasizes REST API and payment integration work.',
                        ],
                        [
                            'position' => 'Senior Web Developer',
                            'status' => JobApplicationStatus::Rejected,
                            'work_mode' => WorkMode::Onsite,
                            'employment_type' => 'full-time',
                            'applied_at' => '2026-05-29',
                            'notes' => 'Closed after the company paused hiring for the quarter.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
