<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('config_discovery_status')->default('idle')->after('last_discovered_at');
            $table->timestamp('config_discovery_started_at')->nullable()->after('config_discovery_status');
            $table->text('config_discovery_error')->nullable()->after('config_discovery_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'config_discovery_status',
                'config_discovery_started_at',
                'config_discovery_error',
            ]);
        });
    }
};
