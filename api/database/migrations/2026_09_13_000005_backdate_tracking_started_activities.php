<?php

use App\Enums\ApplicationActivityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncTrackingStartedDates(preferAppliedDate: true);
    }

    public function down(): void
    {
        $this->syncTrackingStartedDates(preferAppliedDate: false);
    }

    private function syncTrackingStartedDates(bool $preferAppliedDate): void
    {
        DB::table('application_activities')
            ->where('type', ApplicationActivityType::TrackingStarted->value)
            ->orderBy('id')
            ->chunkById(250, static function ($activities) use ($preferAppliedDate): void {
                $applications = DB::table('job_applications')
                    ->whereIn('id', $activities->pluck('job_application_id'))
                    ->get(['id', 'applied_at', 'created_at'])
                    ->keyBy('id');

                foreach ($activities as $activity) {
                    $application = $applications->get($activity->job_application_id);

                    if ($application === null) {
                        continue;
                    }

                    DB::table('application_activities')
                        ->where('id', $activity->id)
                        ->update([
                            'occurred_at' => $preferAppliedDate
                                ? ($application->applied_at ?? $application->created_at)
                                : $application->created_at,
                        ]);
                }
            });
    }
};
