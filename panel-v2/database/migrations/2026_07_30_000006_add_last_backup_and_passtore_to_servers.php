<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('last_backup_at')->nullable()->after('last_discovered_at');
            $table->string('passtore_name')->nullable()->after('name');
            $table->timestamp('passtore_synced_at')->nullable()->after('passtore_name');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['last_backup_at', 'passtore_name', 'passtore_synced_at']);
        });
    }
};
