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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();            
            $table->string('type')->default('onchain_in');
            $table->foreignId('from_wallet_id')->nullable();
            $table->foreignId('to_wallet_id')->nullable();
            $table->foreignId('token_id');
            $table->unsignedBigInteger('quantity');
            $table->string('status')->default('pending');   
            $table->string('receiver_address')->nullable(); 
            $table->string('tx_hash')->nullable();
            $table->unsignedInteger('fee')->nullable();            
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
