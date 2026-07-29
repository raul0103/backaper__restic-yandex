<?php

namespace App\Http\Controllers;

use App\Models\BackupBatch;
use App\Models\Server;
use App\Services\BackupBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BackupBatchController extends Controller
{
    public function create(): View
    {
        $servers = Server::query()
            ->orderBy('name')
            ->get();

        $active = BackupBatch::query()
            ->whereIn('status', ['pending', 'running'])
            ->latest()
            ->first();

        return view('backup-batches.create', [
            'servers' => $servers,
            'activeBatch' => $active,
        ]);
    }

    public function store(Request $request, BackupBatchService $batches): RedirectResponse
    {
        $data = $request->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['integer', 'exists:servers,id'],
            'mode' => ['required', 'in:files,databases,both'],
            'poll_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $readyIds = Server::query()
            ->whereIn('id', $data['server_ids'])
            ->get()
            ->filter(fn (Server $s) => $s->readyForBackup())
            ->pluck('id')
            ->all();

        if ($readyIds === []) {
            return back()->with('error', 'Среди выбранных нет серверов с установленным restic.');
        }

        try {
            $batch = $batches->create(
                $readyIds,
                $data['mode'],
                ((int) ($data['poll_minutes'] ?? 15)) * 60,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->spawnProcessor($batch->id);

        return redirect()
            ->route('backup-batches.show', $batch)
            ->with('success', 'Очередь создана: серверы пойдут по одному, без параллельной нагрузки на Яндекс.');
    }

    public function show(BackupBatch $backupBatch): View
    {
        $backupBatch->load(['items.server', 'items.backupRun', 'currentItem.server']);

        return view('backup-batches.show', ['batch' => $backupBatch]);
    }

    public function status(BackupBatch $backupBatch): JsonResponse
    {
        $backupBatch->load(['items.server', 'items.backupRun', 'currentItem.server']);

        return response()->json([
            'id' => $backupBatch->id,
            'status' => $backupBatch->status,
            'status_label' => $backupBatch->statusLabel(),
            'mode' => $backupBatch->mode,
            'mode_label' => $backupBatch->modeLabel(),
            'message' => $backupBatch->message,
            'poll_seconds' => $backupBatch->poll_seconds,
            'active' => $backupBatch->isActive(),
            'current_server' => $backupBatch->currentItem?->server?->name,
            'items' => $backupBatch->items->map(fn ($item) => [
                'id' => $item->id,
                'server' => $item->server?->name,
                'host' => $item->server?->host,
                'status' => $item->status,
                'status_label' => $item->statusLabel(),
                'message' => $item->message,
                'run_id' => $item->backup_run_id,
                'run_url' => $item->backup_run_id
                    ? route('backup-runs.show', $item->backup_run_id)
                    : null,
            ]),
        ]);
    }

    public function cancel(BackupBatch $backupBatch, BackupBatchService $batches): RedirectResponse
    {
        $batches->cancel($backupBatch);

        return back()->with('success', 'Отмена запрошена. Текущий сервер остановится на следующей проверке; уже запущенный бэкап на SSH может продолжаться.');
    }

    private function spawnProcessor(int $batchId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $id = (int) $batchId;

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' backaper:process-batch '.$id;
            pclose(popen($cmd, 'r'));

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' backaper:process-batch '.$id
            .' >> '.escapeshellarg(storage_path('logs/batch-'.$id.'.log')).' 2>&1 &';
        exec($cmd);
    }
}
