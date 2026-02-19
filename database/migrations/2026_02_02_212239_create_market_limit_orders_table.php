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
        Schema::create('market_limit_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('outcome_id')->nullable();
            $table->unsignedBigInteger('base_token_id')->nullable();
            $table->enum('type', ['BUY','SELL']);
            $table->unsignedBigInteger('limit_price');
            $table->unsignedBigInteger('share_amount');
            $table->integer('filled')->default(0);
            $table->unsignedBigInteger('spent_amount')->default(0);
            $table->enum('status', ['OPEN','PARTIAL','FILLED','CANCELED', 'EXPIRED'])->default('OPEN');
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'market_id', 'outcome_id', 'created_at']);
            $table->index(['market_id', 'outcome_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_limit_orders');
    }
};
