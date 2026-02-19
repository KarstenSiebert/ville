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
        Schema::create('input_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('wallet_id');
            $table->text('inputs');
            $table->text('outputs');
            $table->string('change');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_outputs');
    }
};
