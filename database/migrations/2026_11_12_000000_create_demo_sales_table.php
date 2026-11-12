<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sold_at');
            $table->string('region', 64);
            $table->string('product_id', 32);
            $table->string('product_name', 128);
            $table->unsignedInteger('quantity');
            $table->decimal('total_amount', 12, 2);
            $table->string('salesperson', 128);
            $table->string('team', 64);
            $table->timestamps();

            $table->index(['tenant_id', 'sold_at']);
            $table->index(['tenant_id', 'region']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_sales');
    }
};
