<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('source')->nullable(); // modx | laravel | wordpress | manual
            $table->string('config_path')->nullable();
            $table->string('database_server')->default('localhost');
            $table->string('database_name');
            $table->string('database_user');
            $table->text('database_password')->nullable();
            $table->string('table_prefix')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['server_id', 'database_name', 'database_user']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
