<?php

use App\Enums\ApplicationActivityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('type', 64);
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['job_application_id', 'occurred_at', 'id']);
        });

        DB::table('job_applications')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(250, static function ($applications): void {
                $activities = $applications->map(static fn ($application): array => [
                    'job_application_id' => $application->id,
                    'actor_auth_user_id' => null,
                    'type' => ApplicationActivityType::TrackingStarted->value,
                    'comment' => null,
                    'metadata' => null,
                    'occurred_at' => $application->created_at,
                ])->all();

                if ($activities !== []) {
                    DB::table('application_activities')->insert($activities);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_activities');
    }
};
