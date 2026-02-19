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
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->default(0);
            $table->string('name');
            $table->string('name_hex')->nullable();
            $table->string('policy_id')->nullable();
            $table->string('fingerprint')->unique();
            $table->unsignedInteger('decimals')->default(0);
            $table->unsignedBigInteger('step_size')->default(1);
            $table->text('logo_url')->nullable();
            $table->json('metadata')->nullable();
            $table->string('token_type', 50)->nullable();
            $table->bigInteger('supply')->default(0);
            $table->string('description', 2048)->nullable();
            $table->string('status', 50)->default('active');            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
