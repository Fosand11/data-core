<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_generations', function (Blueprint $table) {
            // "create" runs build a dashboard; "refine" runs propose a change to an existing one.
            $table->string('kind', 16)->default('create')->after('status');
            // Version of the dashboard the proposal was based on; a proposal is stale once that changes.
            $table->unsignedInteger('base_version')->nullable()->after('kind');
            $table->timestamp('applied_at')->nullable()->after('completed_at');
            $table->timestamp('discarded_at')->nullable()->after('applied_at');

            $table->index(['dashboard_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_generations', function (Blueprint $table) {
            $table->dropIndex(['dashboard_id', 'kind', 'created_at']);
            $table->dropColumn(['kind', 'base_version', 'applied_at', 'discarded_at']);
        });
    }
};
