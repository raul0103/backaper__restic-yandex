<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('root_path');
            $table->string('config_path');
            $table->string('database_server')->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_user')->nullable();
            $table->text('database_password')->nullable();
            $table->string('table_prefix')->default('modx_');
            $table->json('exclusions')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['server_id', 'root_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
