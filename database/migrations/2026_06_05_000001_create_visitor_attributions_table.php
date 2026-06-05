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
        Schema::create('visitor_attributions', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('first_landing_page')->nullable();
            $table->text('first_referrer')->nullable();
            $table->string('first_utm_source')->nullable();
            $table->string('first_utm_medium')->nullable();
            $table->string('first_utm_campaign')->nullable();
            $table->string('first_utm_content')->nullable();
            $table->string('first_utm_term')->nullable();
            $table->text('latest_landing_page')->nullable();
            $table->text('latest_referrer')->nullable();
            $table->string('latest_utm_source')->nullable();
            $table->string('latest_utm_medium')->nullable();
            $table->string('latest_utm_campaign')->nullable();
            $table->string('latest_utm_content')->nullable();
            $table->string('latest_utm_term')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'visitor_attributions_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_attributions');
    }
};
