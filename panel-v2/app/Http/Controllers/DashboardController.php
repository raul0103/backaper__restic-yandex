<?php

namespace App\Http\Controllers;

use App\Models\BackupBatch;
use App\Models\BackupRun;
use App\Models\Server;

class DashboardController extends Controller
{
    public function index()
    {
        $activeBatches = BackupBatch::query()
            ->whereIn('status', ['pending', 'running'])
            ->with(['currentItem.server', 'currentItem.backupRun', 'items.server'])
            ->latest()
            ->get();

        $runningRuns = BackupRun::query()
            ->where('status', 'running')
            ->with('server')
            ->latest('started_at')
            ->get();

        // Прогоны из активных очередей уже видны через batch — не дублируем
        $batchRunIds = $activeBatches
            ->flatMap(fn (BackupBatch $b) => $b->items->pluck('backup_run_id'))
            ->filter()
            ->all();

        $standaloneRuns = $runningRuns->reject(
            fn (BackupRun $run) => in_array($run->id, $batchRunIds, true)
        );

        $recentRuns = BackupRun::query()
            ->whereIn('status', ['completed', 'failed'])
            ->with('server')
            ->latest('finished_at')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'servers' => Server::query()->latest()->get(),
            'activeBatches' => $activeBatches,
            'standaloneRuns' => $standaloneRuns,
            'recentRuns' => $recentRuns,
        ]);
    }
}
