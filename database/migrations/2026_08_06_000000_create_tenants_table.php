<?php

use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', array_column(TenantStatus::cases(), 'value'))
                ->default(TenantStatus::Active->value)
                ->index();
            $table->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', array_column(TenantRole::cases(), 'value'));
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
