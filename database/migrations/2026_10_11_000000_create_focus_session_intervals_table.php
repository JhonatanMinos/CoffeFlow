<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_session_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('focus_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX focus_session_intervals_one_open_per_session ON focus_session_intervals (focus_session_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX focus_session_intervals_one_open_per_session');
        Schema::dropIfExists('focus_session_intervals');
    }
};
