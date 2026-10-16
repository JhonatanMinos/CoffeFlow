<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->timestamp('ended_at')->nullable();
        });

        Schema::table('focus_session_intervals', function (Blueprint $table) {
            $table->timestamp('last_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('focus_session_intervals', function (Blueprint $table) {
            $table->dropColumn('last_confirmed_at');
        });

        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->dropColumn(['target_duration_seconds', 'ended_at']);
        });
    }
};
