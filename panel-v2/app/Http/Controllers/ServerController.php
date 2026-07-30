<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\RemoteSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        $queuedServerIds = Server::activeQueueServerIds();

        return view('servers.index', [
            'servers' => Server::listForUi(),
            'queuedServerIds' => $queuedServerIds,
            'passtoreConfigured' => filled(config('services.passtore_token')),
        ]);
    }

    public function syncPasstore(\App\Services\PasstoreSyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->sync();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = "Passtore: всего {$result['total']}, новых {$result['created']}, обновлено {$result['updated']}.";
        if ($result['created'] > 0) {
            $msg .= ' У новых серверов restic ещё не установлен — установите вручную.';
        }

        return redirect()->route('servers.index')->with('success', $msg);
    }

    public function create(): View
    {
        return view('servers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:vps,hosting'],
            'host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['required', 'string', 'max:255'],
            'ssh_password' => ['required', 'string'],
            'restic_password' => ['required', 'string', 'min:8'],
            'restic_repo_slug' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'rclone_token' => ['required', 'string'],
        ]);

        $server = Server::create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'host' => $data['host'],
            'ssh_port' => $data['ssh_port'],
            'ssh_user' => $data['ssh_user'],
            'ssh_auth_type' => Server::AUTH_PASSWORD,
            'ssh_password' => $data['ssh_password'],
            'ssh_private_key' => '',
            'restic_password' => $data['restic_password'] ?: Server::DEFAULT_RESTIC_PASSWORD,
            'restic_repo_slug' => $data['restic_repo_slug']
                ?: (preg_replace('/[^a-zA-Z0-9._-]/', '-', $data['name']) ?: null),
            'rclone_remote' => 'yandex',
            'rclone_token' => $data['rclone_token'],
            'setup_step' => Server::STEP_INSTALL,
        ]);

        $server->syncFullBackupPath();

        return redirect()
            ->route('servers.wizard.install', $server)
            ->with('success', 'Сервер добавлен. Теперь установите restic на сервер.');
    }

    public function restoreGuide(Server $server): View|RedirectResponse
    {
        if (! $server->is_setup_complete) {
            return redirect()->route('servers.edit', $server)
                ->with('error', 'Сначала установите restic на сервере.');
        }

        $server->load('backupPaths');

        return view('servers.restore', compact('server'));
    }

    public function edit(Server $server): View
    {
        return view('servers.edit', compact('server'));
    }

    public function update(Request $request, Server $server): RedirectResponse
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
            'rclone_token' => ['nullable', 'string'],
        ]);

        $newSlug = $data['restic_repo_slug']
            ?: (preg_replace('/[^a-zA-Z0-9._-]/', '-', $data['name']) ?: null);

        $cloudChanged = $data['restic_password'] !== $server->restic_password
            || (string) $newSlug !== (string) $server->restic_repo_slug
            || (! empty($data['rclone_token']) && trim($data['rclone_token']) !== trim((string) $server->rclone_token));

        $payload = [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'host' => $data['host'],
            'ssh_port' => $data['ssh_port'],
            'ssh_user' => $data['ssh_user'],
            'restic_password' => $data['restic_password'],
            'restic_repo_slug' => $newSlug,
        ];

        if (! empty($data['ssh_password'])) {
            $payload['ssh_password'] = $data['ssh_password'];
        }
        if (array_key_exists('rclone_token', $data) && $data['rclone_token'] !== null && $data['rclone_token'] !== '') {
            $payload['rclone_token'] = $data['rclone_token'];
        }

        if ($cloudChanged) {
            $payload['is_setup_complete'] = false;
            $payload['setup_log'] = null;
            $payload['setup_step'] = Server::STEP_INSTALL;
        }

        $server->update($payload);
        $server->syncFullBackupPath();

        if ($cloudChanged) {
            return redirect()
                ->route('servers.wizard.install', $server)
                ->with('warning', 'Изменены данные облака/пароля. Нужно переустановить restic на сервере.');
        }

        return redirect()->route('servers.edit', $server)->with('success', 'Сохранено.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        if (in_array($server->id, Server::activeQueueServerIds(), true)) {
            return back()->with('error', 'Нельзя удалить: сервер сейчас в активной очереди. Дождитесь завершения или отмените очередь.');
        }

        $name = $server->name;
        $server->delete();

        return redirect()->route('servers.index')->with('success', "Сервер «{$name}» удалён из панели (бэкапы на Яндекс.Диске не трогали).");
    }

    public function setup(Server $server, RemoteSetupService $setup): RedirectResponse
    {
        try {
            $log = $setup->setup($server);
            if ($server->fresh()->is_setup_complete) {
                $server->update(['setup_step' => Server::STEP_COMPLETE]);

                return back()->with('success', 'restic переустановлен: конфиг, репозиторий и CLI-скрипты готовы.');
            }

            return back()->with('error', 'Установка не завершилась.'.$this->shortLog($log));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function shortLog(string $log): string
    {
        $tail = trim(substr($log, -400));

        return $tail !== '' ? ' Лог: '.$tail : '';
    }
}
