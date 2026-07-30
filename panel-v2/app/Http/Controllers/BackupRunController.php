<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Services\BackupLogParser;
use App\Services\BackupOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackupRunController extends Controller
{
    public function index(Request $request): View
    {
        $query = BackupRun::query()->with('server')->latest('id');

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, ['running', 'completed', 'failed', 'pending'], true)) {
                $query->where('status', $status);
            }
        }

        if ($serverId = $request->integer('server_id')) {
            $query->where('server_id', $serverId);
        }

        return view('backup-runs.index', [
            'runs' => $query->paginate(30)->withQueryString(),
            'filterStatus' => $request->string('status')->toString(),
        ]);
    }

    public function show(BackupRun $backupRun): View
    {
        $backupRun->load(['server']);

        return view('backup-runs.show', ['run' => $backupRun]);
    }

    public function status(BackupRun $backupRun, BackupOrchestrator $orchestrator, BackupLogParser $parser): JsonResponse
    {
        set_time_limit(120);

        if ($backupRun->isRunning()) {
            $orchestrator->advanceBackup($backupRun);
        }

        $backupRun->refresh();
        $log = (string) ($backupRun->log ?? '');
        if ($backupRun->isRunning() && trim($log) === '') {
            $peek = $orchestrator->peekLog($backupRun);
            if ($peek !== '') {
                $log = $peek;
                $backupRun->update(['log' => $peek]);
            } else {
                $log = "Ожидание строк в run.log на сервере…\n"
                    ."PID: ".($backupRun->remote_pid ?: '—')."\n"
                    ."Проверьте: tail -f ~/.backaper/backup-{$backupRun->id}/run.log";
            }
        }

        return response()->json([
            'status' => $backupRun->status,
            'running' => $backupRun->isRunning(),
            'log' => $log,
            'sizes' => $parser->parse($log),
            'remote_pid' => $backupRun->remote_pid,
            'started_at' => $backupRun->started_at?->toIso8601String(),
            'finished_at' => $backupRun->finished_at?->toIso8601String(),
        ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
