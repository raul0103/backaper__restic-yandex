<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user');
            $table->text('ssh_private_key');
            $table->json('search_paths')->nullable();
            $table->string('restic_password');
            $table->string('rclone_remote')->default('yandex');
            $table->text('rclone_token')->nullable();
            $table->string('restic_repo_slug')->nullable();
            $table->boolean('is_setup_complete')->default(false);
            $table->text('setup_log')->nullable();
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
