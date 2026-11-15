<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the dashboard is drawn (palette and per-chart colours). It belongs to the
        // viewer-facing look, not to the AI-generated spec, so a refinement never touches it.
        Schema::table('dashboards', function (Blueprint $table) {
            $table->json('appearance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn('appearance');
        });
    }
};
