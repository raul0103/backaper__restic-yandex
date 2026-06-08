<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Services\BackupLogParser;
use App\Services\BackupOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BackupRunController extends Controller
{
    public function show(BackupRun $backupRun): View
    {
        $backupRun->load(['server', 'project']);

        return view('backup-runs.show', ['run' => $backupRun]);
    }

    public function status(BackupRun $backupRun, BackupOrchestrator $orchestrator, BackupLogParser $parser): JsonResponse
    {
        set_time_limit(120);

        if ($backupRun->isRunning()) {
            $orchestrator->advanceBackup($backupRun);
        }

        $backupRun->refresh();

        return response()->json([
            'status' => $backupRun->status,
            'running' => $backupRun->isRunning(),
            'log' => $backupRun->log,
            'sizes' => $parser->parse($backupRun->log),
            'remote_pid' => $backupRun->remote_pid,
            'started_at' => $backupRun->started_at?->toIso8601String(),
            'finished_at' => $backupRun->finished_at?->toIso8601String(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
