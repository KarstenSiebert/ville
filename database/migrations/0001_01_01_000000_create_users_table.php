<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {        
        Schema::create('users', function (Blueprint $table) {
            $table->id();          
            $table->foreignId('parent_user_id')->nullable();
            $table->foreignId('owner_user_id')->nullable();
            $table->foreignId('publisher_id')->nullable();
            $table->foreignId('external_user_id')->nullable();
            $table->string('name');
            $table->string('type')->nullable()->default('USER');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();            
            $table->string('device_id', 255)->nullable();
            $table->string('public_id', 2048)->nullable();
            $table->string('profile_photo_path', 2048)->nullable();
            $table->string('payout')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();            
        });
        
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE email IS NOT NULL AND deleted_at IS NULL');            

            DB::statement("CREATE UNIQUE INDEX users_publisher_name_unique ON users (name) WHERE type = 'OPERATOR' AND deleted_at IS NULL");

            DB::statement('CREATE UNIQUE INDEX users_publisher_device_unique ON users (publisher_id, device_id) WHERE deleted_at IS NULL AND device_id IS NOT NULL');

            DB::statement('CREATE UNIQUE INDEX users_type_external_unique ON users (external_user_id, type) WHERE deleted_at IS NULL AND external_user_id IS NOT NULL');

            DB::statement('CREATE UNIQUE INDEX users_publisher_external_unique ON users (publisher_id, external_user_id) WHERE deleted_at IS NULL AND external_user_id IS NOT NULL');
        }        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
        }
        
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
