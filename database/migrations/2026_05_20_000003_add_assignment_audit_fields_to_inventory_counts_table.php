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
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('tasked_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('tasked_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropConstrainedForeignId('tasked_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
