<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->index();
            $table->string('module', 32);
            $table->string('uuid')->unique();
            $table->string('title');
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->index(['team_id', 'module']);
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('reason')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('blocked_until')->nullable();
            $table->boolean('permanent')->default(false);
            $table->boolean('approved')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('purpose', 32)->default('login');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_email')->nullable();
        });

        Schema::create('maintenance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique();
            $table->boolean('daily_enabled')->default(false);
            $table->string('daily_time', 5)->default('03:30');
            $table->boolean('weekly_enabled')->default(false);
            $table->string('weekly_day', 9)->default('sunday');
            $table->string('weekly_time', 5)->default('04:30');
            $table->json('options')->nullable();
            $table->timestamp('last_daily_at')->nullable();
            $table->timestamp('last_weekly_at')->nullable();
            $table->timestamps();
        });

        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('host')->default('127.0.0.1');
            $table->unsignedSmallInteger('port')->default(1025);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption', 16)->default('none');
            $table->string('from_address')->default('dashy@localhost');
            $table->string('from_name')->default('Dashy');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
        Schema::dropIfExists('maintenance_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_email']);
        });
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('module_records');
    }
};
