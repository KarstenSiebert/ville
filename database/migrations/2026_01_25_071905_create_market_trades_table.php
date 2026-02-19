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
        Schema::create('market_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('outcome_id')->nullable();

            $table->unsignedBigInteger('share_amount');
            $table->unsignedBigInteger('price_paid');
            $table->unsignedBigInteger('price_numerator')->nullable();
            $table->unsignedBigInteger('price_denominator')->nullable();
            
            $table->enum('tx_type', ['BUY', 'SELL', 'REFUND', 'SETTLE', 'ADJUST', 'CANCEL'])->default('BUY');

            $table->string('tx_hash')->nullable();
            $table->index(['market_id', 'outcome_id']);
            $table->index(['user_id']);

            $table->foreignId('cancelled_trade_id')->nullable();
        
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['market_id', 'created_at']);
            $table->index(['outcome_id', 'created_at']);
            $table->index(['tx_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_trades');
    }
};
