<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX focus_sessions_one_active_per_user ON focus_sessions (user_id) WHERE status IN ('running', 'paused')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX focus_sessions_one_active_per_user');
        Schema::dropIfExists('focus_sessions');
    }
};
