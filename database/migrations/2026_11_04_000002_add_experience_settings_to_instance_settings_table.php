<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('instance_settings', 'is_boot_screen_enabled')) {
                $table->boolean('is_boot_screen_enabled')->default(true);
            }
            if (! Schema::hasColumn('instance_settings', 'is_cli_pairing_enabled')) {
                $table->boolean('is_cli_pairing_enabled')->default(true);
            }
            if (! Schema::hasColumn('instance_settings', 'metrics_refresh_seconds')) {
                $table->unsignedSmallInteger('metrics_refresh_seconds')->default(15);
            }
            if (! Schema::hasColumn('instance_settings', 'metrics_retention_days')) {
                $table->unsignedSmallInteger('metrics_retention_days')->default(30);
            }
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_boot_screen_enabled',
                'is_cli_pairing_enabled',
                'metrics_refresh_seconds',
                'metrics_retention_days',
            ]);
        });
    }
};
