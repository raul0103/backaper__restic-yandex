<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->string('mode')->default('both'); // files|databases|both
            $table->unsignedInteger('poll_seconds')->default(900);
            $table->foreignId('current_item_id')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('pending');
            $table->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('backup_batches', function (Blueprint $table) {
            $table->foreign('current_item_id')
                ->references('id')
                ->on('backup_batch_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('backup_batches', function (Blueprint $table) {
            $table->dropForeign(['current_item_id']);
        });
        Schema::dropIfExists('backup_batch_items');
        Schema::dropIfExists('backup_batches');
    }
};
