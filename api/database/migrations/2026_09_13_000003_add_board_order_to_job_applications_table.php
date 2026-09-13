<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->unsignedInteger('board_order')->default(0)->after('status');
            $table->index(['status', 'board_order']);
        });

        DB::table('job_applications')->update(['board_order' => DB::raw('id')]);
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropIndex(['status', 'board_order']);
            $table->dropColumn('board_order');
        });
    }
};
