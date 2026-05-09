<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_customer_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_customer_id');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'source', 'external_customer_id'],
                'external_customer_mappings_tenant_source_external_unique'
            );
            $table->index(['tenant_id', 'customer_id'], 'external_customer_mappings_tenant_customer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_customer_mappings');
    }
};
