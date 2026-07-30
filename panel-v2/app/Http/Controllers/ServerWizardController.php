<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\RemoteSetupService;
use App\Services\SshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerWizardController extends Controller
{
    public function connect(Server $server): View|RedirectResponse
    {
        if ($server->isWizardComplete()) {
            return redirect()->route('servers.edit', $server);
        }

        return view('servers.wizard.connect', compact('server'));
    }

    public function updateConnect(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:vps,hosting'],
            'host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['required', 'string', 'max:255'],
            'ssh_password' => ['nullable', 'string'],
            'restic_password' => ['required', 'string', 'min:8'],
            'restic_repo_slug' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'rclone_token' => ['required', 'string'],
        ]);

        $payload = [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'host' => $data['host'],
            'ssh_port' => $data['ssh_port'],
            'ssh_user' => $data['ssh_user'],
            'restic_password' => $data['restic_password'],
            'restic_repo_slug' => $data['restic_repo_slug']
                ?: (preg_replace('/[^a-zA-Z0-9._-]/', '-', $data['name']) ?: null),
            'rclone_token' => $data['rclone_token'],
            'rclone_remote' => 'yandex',
            'setup_step' => max($server->setup_step, Server::STEP_INSTALL),
        ];
        if (! empty($data['ssh_password'])) {
            $payload['ssh_password'] = $data['ssh_password'];
        }

        $server->update($payload);
        $server->syncFullBackupPath();

        return redirect()->route('servers.wizard.install', $server)
            ->with('success', 'Сохранено. Дальше — установка на сервер.');
    }

    public function install(Server $server): View|RedirectResponse
    {
        if ($server->isWizardComplete()) {
            return redirect()->route('servers.edit', $server);
        }
        if (! $server->readyForRemoteSetup()) {
            return redirect()->route('servers.wizard.connect', $server)
                ->with('error', 'Сначала заполните подключение и токен Яндекс.Диска.');
        }

        return view('servers.wizard.install', compact('server'));
    }

    public function runInstall(Server $server, RemoteSetupService $setup): RedirectResponse
    {
        set_time_limit(300);

        try {
            $log = $setup->setup($server);
            if (! $server->fresh()->is_setup_complete) {
                return back()->with('error', 'Установка не удалась. Проверьте SSH и токен. '.substr($log, -500));
            }

            $server->update(['setup_step' => Server::STEP_COMPLETE]);

            return redirect()->route('servers.index')
                ->with('success', 'Готово! restic установлен. Бэкапы — из списка серверов или вручную по SSH (Справка).');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function testConnection(Server $server, SshService $ssh): RedirectResponse
    {
        try {
            $who = trim($ssh->exec($server, 'whoami; hostname; pwd', 20));

            return back()->with('success', 'SSH ок: '.$who);
        } catch (\Throwable $e) {
            return back()->with('error', 'SSH ошибка: '.$e->getMessage());
        }
    }
}
