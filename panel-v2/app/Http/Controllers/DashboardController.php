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

        $batchRunIds = $activeBatches
            ->flatMap(fn (BackupBatch $b) => $b->items->pluck('backup_run_id'))
            ->filter()
            ->all();

        $standaloneRuns = $runningRuns->reject(
            fn (BackupRun $run) => in_array($run->id, $batchRunIds, true)
        );

        return view('dashboard', [
            'servers' => Server::listForUi(),
            'queuedServerIds' => Server::activeQueueServerIds(),
            'activeBatches' => $activeBatches,
            'standaloneRuns' => $standaloneRuns,
        ]);
    }
}
