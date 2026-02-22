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
        Schema::create('markets', function (Blueprint $table) {
            $table->id();    
            $table->foreignId('user_id');
            $table->foreignId('wallet_id');
            $table->foreignId('publisher_id')->nullable();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->text('logo_url')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED', 'RESOLVED', 'SETTLED', 'CANCELED'])->default('OPEN');
            $table->foreignId('winning_outcome_id')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('close_time')->nullable();
            $table->unsignedBigInteger('liquidity_b')->default(100);
            $table->string('base_token_fingerprint')->nullable();
            $table->unsignedBigInteger('b');
            $table->unsignedBigInteger('min_trade_amount')->default(1);
            $table->unsignedBigInteger('max_trade_amount')->default(1000);
            $table->unsignedBigInteger('resolved_outcome_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->boolean('allow_limit_orders')->default(false);
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'base_token_fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
