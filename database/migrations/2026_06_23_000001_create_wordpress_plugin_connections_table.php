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
        Schema::create('wordpress_plugin_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_uuid');
            $table->text('site_url');
            $table->string('site_name')->nullable();
            $table->string('status')->default('connected');
            $table->string('access_token_hash')->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'plugin_uuid'], 'wordpress_plugin_connections_tenant_uuid_unique');
            $table->index(['tenant_id', 'status'], 'wordpress_plugin_connections_tenant_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordpress_plugin_connections');
    }
};
