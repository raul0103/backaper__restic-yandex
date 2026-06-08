<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedTinyInteger('setup_step')->default(1)->after('ssh_user');
            $table->text('ssh_public_key')->nullable()->after('ssh_private_key');
        });

        if (Schema::hasColumn('servers', 'search_paths')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('search_paths');
            });
        }

        Schema::create('modx_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('config_path');
            $table->string('suggested_root_path')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'config_path']);
        });

        Schema::create('project_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modx_config_id')->constrained()->cascadeOnDelete();
            $table->string('database_server')->default('localhost');
            $table->string('database_name');
            $table->string('database_user');
            $table->text('database_password')->nullable();
            $table->string('table_prefix')->default('modx_');
            $table->timestamps();

            $table->unique('modx_config_id');
        });

        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('projects');

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modx_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_database_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('root_path');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique('modx_config_id');
        });

        Schema::create('project_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->longText('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('project_exclusions');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_databases');
        Schema::dropIfExists('modx_configs');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['setup_step', 'ssh_public_key']);
            $table->json('search_paths')->nullable();
        });
    }
};
