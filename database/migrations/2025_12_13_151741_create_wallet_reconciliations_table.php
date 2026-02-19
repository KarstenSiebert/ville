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
        Schema::create('wallet_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->nullable();
            $table->foreignId('wallet_id');
            $table->foreignId('token_id');
            $table->bigInteger('quantity_before')->default(0);
            $table->bigInteger('quantity_after')->default(0);
            $table->bigInteger('change')->default(0);
            $table->string('tx_hash')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_reconciliations');
    }
};
