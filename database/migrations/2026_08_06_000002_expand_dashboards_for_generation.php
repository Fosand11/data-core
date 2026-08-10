<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->renameColumn('name', 'title');
        });

        Schema::table('dashboards', function (Blueprint $table) {
            // Nullable only for Phase 1 rows, which predate generation metadata.
            $table->foreignId('created_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('title');
            $table->text('original_prompt')->nullable()->after('description');
            $table->string('status', 20)->default('archived')->after('original_prompt');
            $table->string('spec_version', 16)->nullable()->after('status');
            $table->jsonb('dashboard_spec')->nullable()->after('spec_version');
            $table->string('provider', 64)->nullable()->after('dashboard_spec');
            $table->text('error_message')->nullable()->after('provider');
            $table->timestamp('generation_started_at')->nullable()->after('error_message');
            $table->timestamp('generation_completed_at')->nullable()->after('generation_started_at');

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'description',
                'original_prompt',
                'status',
                'spec_version',
                'dashboard_spec',
                'provider',
                'error_message',
                'generation_started_at',
                'generation_completed_at',
            ]);
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->renameColumn('title', 'name');
        });
    }
};
