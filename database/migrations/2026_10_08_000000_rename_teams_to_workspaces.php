<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('teams', 'workspaces');
        Schema::rename('team_members', 'workspace_members');
        Schema::rename('team_invitations', 'workspace_invitations');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->enum('type', ['personal', 'team'])->default('team')->index();
        });

        DB::table('workspaces')
            ->where('is_personal', true)
            ->update(['type' => 'personal']);

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('is_personal');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->renameColumn('team_id', 'workspace_id');
            $table->index(['user_id', 'role']);
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->renameColumn('team_id', 'workspace_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_team_id', 'current_workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_workspace_id', 'current_team_id');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'team_id');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'role']);
            $table->renameColumn('workspace_id', 'team_id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('is_personal')->default(false);
        });

        DB::table('workspaces')
            ->where('type', 'personal')
            ->update(['is_personal' => true]);

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::rename('workspace_invitations', 'team_invitations');
        Schema::rename('workspace_members', 'team_members');
        Schema::rename('workspaces', 'teams');
    }
};
