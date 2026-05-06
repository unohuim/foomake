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
        Schema::create('external_product_source_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('connection_label')->nullable();
            $table->boolean('is_connected')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'source'],
                'external_product_source_connections_tenant_source_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_product_source_connections');
    }
};
