<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history of a dashboard's spec. The highest number is the current one.
        Schema::create('dashboard_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('dashboard_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 16);
            $table->text('instruction')->nullable();
            $table->string('spec_version', 16);
            $table->jsonb('spec');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dashboard_id', 'number']);
            $table->index(['tenant_id', 'dashboard_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_versions');
    }
};
