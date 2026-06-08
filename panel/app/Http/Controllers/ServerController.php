<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\BackupOrchestrator;
use App\Services\RemoteSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        return view('servers.index', [
            'servers' => Server::withCount(['projects', 'modxConfigs', 'projectDatabases'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('servers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['required', 'string', 'max:255'],
            'ssh_password' => ['required', 'string'],
            'restic_password' => ['required', 'string', 'min:8'],
            'rclone_remote' => ['nullable', 'string', 'max:64'],
            'rclone_token' => ['nullable', 'string'],
        ]);

        $server = Server::create([
            'name' => $data['name'],
            'host' => $data['host'],
            'ssh_port' => $data['ssh_port'],
            'ssh_user' => $data['ssh_user'],
            'ssh_auth_type' => Server::AUTH_PASSWORD,
            'ssh_password' => $data['ssh_password'],
            'ssh_private_key' => '',
            'ssh_public_key' => null,
            'restic_password' => $data['restic_password'],
            'rclone_remote' => $data['rclone_remote'] ?: 'yandex',
            'rclone_token' => $data['rclone_token'] ?? null,
            'setup_step' => Server::STEP_SETTINGS,
        ]);

        return redirect()
            ->route('servers.wizard.step2', $server)
            ->with('success', 'Сервер создан. На шаге 2 найдите конфиги MODX.');
    }

    public function show(Server $server): View|RedirectResponse
    {
        if (! $server->isWizardComplete()) {
            return redirect()->route($server->wizardRoute(), $server);
        }

        $server->load([
            'projects.database',
            'projects.modxConfig',
            'projects.exclusionRules',
            'backupRuns' => fn ($q) => $q->latest()->limit(20),
        ]);

        return view('servers.show', compact('server'));
    }

    public function edit(Server $server): View|RedirectResponse
    {
        if (! $server->isWizardComplete()) {
            return redirect()->route($server->wizardRoute(), $server);
        }

        return redirect()->route('servers.wizard.step1', $server);
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['required', 'string', 'max:255'],
            'ssh_password' => ['nullable', 'string'],
            'restic_password' => ['required', 'string', 'min:8'],
            'rclone_remote' => ['nullable', 'string', 'max:64'],
            'rclone_token' => ['nullable', 'string'],
        ]);

        $update = [
            'name' => $data['name'],
            'host' => $data['host'],
            'ssh_port' => $data['ssh_port'],
            'ssh_user' => $data['ssh_user'],
            'ssh_auth_type' => Server::AUTH_PASSWORD,
            'restic_password' => $data['restic_password'],
            'rclone_remote' => $data['rclone_remote'] ?: 'yandex',
        ];

        if (filled($data['rclone_token'] ?? null)) {
            $update['rclone_token'] = $data['rclone_token'];
        }

        if (! empty($data['ssh_password'])) {
            $update['ssh_password'] = $data['ssh_password'];
        }

        $server->update($update);

        return redirect()->route('servers.show', $server)->with('success', 'Сервер обновлён');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()->route('servers.index')->with('success', 'Сервер удалён');
    }

    public function setup(Server $server, RemoteSetupService $setup): RedirectResponse
    {
        if (! $server->readyForRemoteSetup()) {
            return back()->with('error', 'Укажите RESTIC_PASSWORD и Rclone token на шаге 1 мастера.');
        }

        try {
            $log = $setup->setup($server);
            $message = $server->fresh()->is_setup_complete
                ? 'restic + rclone установлены на сервере'
                : 'Ошибка установки — см. лог';
        } catch (\Throwable $e) {
            $server->update(['setup_log' => $e->getMessage(), 'is_setup_complete' => false]);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message)->with('setup_log', $log);
    }

    public function setupAll(RemoteSetupService $setup): RedirectResponse
    {
        $logs = $setup->setupAll();

        if ($logs === []) {
            return back()->with('error', 'Нет серверов, готовых к установке.');
        }

        $ok = collect($logs)->filter(fn ($l) => str_contains($l, 'SETUP_COMPLETE'))->count();

        return back()->with('success', "Установка: {$ok}/".count($logs).' сервер(ов)');
    }

    public function backup(Server $server, BackupOrchestrator $orchestrator): RedirectResponse
    {
        try {
            $run = $orchestrator->startServerBackup($server);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($run->status === 'failed') {
            return redirect()->route('backup-runs.show', $run)->with('error', 'Не удалось запустить бэкап');
        }

        return redirect()->route('backup-runs.show', $run)->with(
            'success',
            'Бэкап запущен на сервере'.($run->remote_pid ? " (PID {$run->remote_pid})" : '').'. Статус обновится автоматически.'
        );
    }

    public function backupAll(BackupOrchestrator $orchestrator): RedirectResponse
    {
        $servers = Server::all()->filter(fn (Server $s) => $s->readyForBackup());

        if ($servers->isEmpty()) {
            return back()->with('error', 'Нет серверов, готовых к бэкапу.');
        }

        $started = 0;
        $failed = 0;
        $lastRun = null;

        foreach ($servers as $server) {
            try {
                $run = $orchestrator->startServerBackup($server);
                $lastRun = $run;
                if ($run->isRunning()) {
                    $started++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($started === 1 && $lastRun) {
            return redirect()->route('backup-runs.show', $lastRun)->with(
                'success',
                'Бэкап запущен на сервере. Статус обновится автоматически.'
            );
        }

        return back()->with('success', "Запущено бэкапов: {$started}".($failed > 0 ? ", ошибок запуска: {$failed}" : ''));
    }
}
