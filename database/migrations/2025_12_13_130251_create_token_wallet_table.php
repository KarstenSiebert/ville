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
        Schema::create('token_wallet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id');
            $table->foreignId('token_id');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->unsignedBigInteger('quantity_version')->default(0);
            $table->unsignedBigInteger('reserved_quantity')->default(0);
            $table->string('status', 20)->default('active');            
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['wallet_id', 'token_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_wallet');
    }
};
