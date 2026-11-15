<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 16);
            $table->string('source_filename', 255);
            $table->string('source_type', 8);
            // Where the uploaded file waits until it is imported; cleared afterwards.
            $table->string('source_path', 255)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            // Cells that could not be read as the column's type and were left empty.
            $table->unsignedInteger('warning_count')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('dataset_columns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            // The name dashboards use for the column (a safe identifier derived from the header).
            $table->string('key', 64);
            // The header exactly as it appeared in the file.
            $table->string('label', 255);
            $table->string('type', 16);
            // The physical column in dataset_rows (s1.., n1.., d1..); set when the import is confirmed.
            $table->string('slot', 8)->nullable();
            $table->boolean('included')->default(true);
            // A few example values for the preview. Never sent to an AI provider.
            $table->json('samples')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['dataset_id', 'key']);
        });

        Schema::create('dataset_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();

            // Typed slots (16 string, 12 numeric, 4 datetime; keep config/datasets.php in step, a test checks it).
            // A dataset's columns are mapped onto these, so queries only ever
            // name a fixed, known set of physical columns and never a user-chosen one.
            foreach (range(1, 16) as $i) {
                $table->string("s{$i}", 255)->nullable();
            }

            foreach (range(1, 12) as $i) {
                $table->decimal("n{$i}", 24, 6)->nullable();
            }

            foreach (range(1, 4) as $i) {
                $table->timestamp("d{$i}")->nullable();
            }

            $table->index(['tenant_id', 'dataset_id']);
        });

        Schema::table('dashboards', function (Blueprint $table): void {
            // Null means the built-in demo dataset.
            $table->foreignUuid('dataset_id')->nullable()->after('tenant_id')->constrained('datasets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dataset_id');
        });

        Schema::dropIfExists('dataset_rows');
        Schema::dropIfExists('dataset_columns');
        Schema::dropIfExists('datasets');
    }
};
