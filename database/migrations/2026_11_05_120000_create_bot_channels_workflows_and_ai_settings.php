<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->index();
            $table->string('platform', 32);
            $table->boolean('enabled')->default(false);
            $table->text('bot_token')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('target')->nullable();
            $table->string('embed_color', 7)->default('#6b16ed');
            $table->json('features')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'platform']);
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('team_id')->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('trigger', 16)->default('manual');
            $table->string('cron')->nullable();
            $table->json('steps')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique();
            $table->string('provider', 32)->default('openai');
            $table->string('base_url')->default('https://api.openai.com/v1');
            $table->text('api_key')->nullable();
            $table->string('model')->default('gpt-4o-mini');
            $table->text('system_prompt')->nullable();
            $table->boolean('allow_command_execution')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('bot_channels');
    }
};
