<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Server;
use App\Services\ConfigDiscoveryCanceller;
use App\Services\ConfigDiscoveryService;
use App\Services\DatabaseExtractionService;
use App\Services\SshService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServerWizardController extends Controller
{
    public function step1(Server $server): View|RedirectResponse
    {
        if ($redirect = $this->guardWizardStep($server, 1)) {
            return $redirect;
        }

        return view('servers.wizard.step1', compact('server'));
    }

    public function updateStep1(Request $request, Server $server): RedirectResponse
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

        if ($server->isWizardComplete()) {
            return redirect()
                ->route('servers.show', $server)
                ->with('success', 'SSH и restic/rclone сохранены. Можно нажать «Установить restic».');
        }

        return back()->with('success', 'Настройки сохранены');
    }

    public function step2(Server $server): View|RedirectResponse
    {
        if ($redirect = $this->guardWizardStep($server, 2)) {
            return $redirect;
        }

        $server->resetStaleDiscovery();
        $server->load('modxConfigs');

        return view('servers.wizard.step2', compact('server'));
    }

    public function testConnection(Server $server, SshService $ssh): RedirectResponse
    {
        try {
            $ssh->exec($server, 'echo ok');
        } catch (\Throwable $e) {
            return back()->with('error', 'SSH: '.$e->getMessage());
        }

        return back()->with('success', "SSH-подключение работает: {$server->ssh_user}@{$server->host}");
    }

    public function discoverConfigs(Request $request, Server $server, ConfigDiscoveryService $discovery): RedirectResponse|JsonResponse
    {
        set_time_limit(120);

        $server->resetStaleDiscovery();
        $server->refresh();

        if ($server->isDiscoveryRunning()) {
            $payload = [
                'ok' => true,
                'running' => true,
                'message' => 'Поиск уже выполняется',
            ];

            return $request->wantsJson()
                ? response()->json($payload, 200, [], JSON_INVALID_UTF8_SUBSTITUTE)
                : back()->with('success', $payload['message']);
        }

        try {
            $server->markDiscoveryRunning();
            $pid = $discovery->startRemote($server);
        } catch (\Throwable $e) {
            $server->markDiscoveryFailed($e->getMessage());

            $payload = [
                'ok' => false,
                'running' => false,
                'status' => $server->fresh()->config_discovery_status,
                'error' => $e->getMessage(),
            ];

            return $request->wantsJson()
                ? response()->json($payload, 422, [], JSON_INVALID_UTF8_SUBSTITUTE)
                : back()->with('error', $e->getMessage());
        }

        $message = "Поиск запущен на сервере (PID {$pid})";

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'running' => true,
                'remote_pid' => $pid,
                'message' => $message,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return back()->with('success', $message.'. Статус обновится автоматически.');
    }

    public function cancelDiscovery(Server $server, ConfigDiscoveryCanceller $canceller): JsonResponse
    {
        $cancelled = $canceller->cancel($server);
        $server->refresh();
        $server->load('modxConfigs');

        return $this->discoveryJson($server, [
            'cancelled' => $cancelled,
        ]);
    }

    public function discoveryStatus(Server $server, ConfigDiscoveryService $discovery): JsonResponse
    {
        set_time_limit(120);

        $server->resetStaleDiscovery();
        $server->refresh();

        if ($server->isDiscoveryRunning()) {
            $discovery->advanceDiscovery($server);
        }

        $server->refresh();
        $server->load('modxConfigs');

        return $this->discoveryJson($server);
    }

    /** @param array<string, mixed> $extra */
    private function discoveryJson(Server $server, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => $server->config_discovery_status,
            'running' => $server->isDiscoveryRunning(),
            'found' => $server->modxConfigs->count(),
            'error' => $server->config_discovery_error,
            'started_at' => $server->config_discovery_started_at?->toIso8601String(),
            'remote_pid' => $server->config_discovery_remote_pid,
            'configs' => $server->modxConfigs->map(fn ($c) => [
                'id' => $c->id,
                'config_path' => $c->config_path,
                'suggested_root_path' => $c->suggested_root_path,
            ])->values(),
            'proceed_url' => route('servers.wizard.step3.go', $server),
        ], $extra), 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function proceedToStep3(Server $server): RedirectResponse
    {
        if ($server->modxConfigs()->count() === 0) {
            return back()->with('error', 'Сначала найдите конфиги MODX');
        }

        $server->update(['setup_step' => Server::STEP_DATABASES]);

        return redirect()->route('servers.wizard.step3', $server);
    }

    public function step3(Server $server): View|RedirectResponse
    {
        if ($redirect = $this->guardWizardStep($server, 3)) {
            return $redirect;
        }

        $server->load(['modxConfigs.database']);

        return view('servers.wizard.step3', compact('server'));
    }

    public function extractDatabases(Server $server, DatabaseExtractionService $extraction): RedirectResponse
    {
        if ($server->modxConfigs()->count() === 0) {
            return back()->with('error', 'Нет конфигов — вернитесь на шаг 2');
        }

        try {
            $result = $extraction->extract($server);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['extracted'] === 0) {
            return back()->with('error', 'Не удалось извлечь данные БД из конфигов');
        }

        $message = "Баз данных извлечено: {$result['extracted']}";
        if ($result['failed'] > 0) {
            $message .= ", ошибок: {$result['failed']}";
        }

        return back()->with('success', $message);
    }

    public function proceedToStep4(Server $server): RedirectResponse
    {
        if ($server->projectDatabases()->count() === 0) {
            return back()->with('error', 'Сначала извлеките базы данных из конфигов');
        }

        $server->update(['setup_step' => Server::STEP_PROJECTS]);

        return redirect()->route('servers.wizard.step4', $server);
    }

    public function step4(Server $server): View|RedirectResponse
    {
        if ($redirect = $this->guardWizardStep($server, 4)) {
            return $redirect;
        }

        $server->load(['modxConfigs.database', 'modxConfigs.project.exclusionRules']);

        return view('servers.wizard.step4', compact('server'));
    }

    public function finishStep4(Request $request, Server $server): RedirectResponse
    {
        $configs = $server->modxConfigs()->with('database')->get();

        if ($configs->isEmpty()) {
            return back()->with('error', 'Нет конфигов для настройки проектов');
        }

        $validated = $request->validate([
            'projects' => ['required', 'array'],
            'projects.*.enabled' => ['nullable'],
            'projects.*.name' => ['required', 'string', 'max:255'],
            'projects.*.root_path' => ['required', 'string', 'max:1024'],
            'projects.*.exclusions' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($server, $configs, $validated) {
            foreach ($configs as $config) {
                if (! $config->database) {
                    continue;
                }

                $input = $validated['projects'][$config->id] ?? null;
                if (! $input) {
                    continue;
                }

                $enabled = isset($input['enabled']) && $input['enabled'];

                if (! $enabled) {
                    Project::query()->where('modx_config_id', $config->id)->delete();

                    continue;
                }

                $project = Project::query()->updateOrCreate(
                    ['modx_config_id' => $config->id],
                    [
                        'server_id' => $server->id,
                        'project_database_id' => $config->database->id,
                        'name' => $input['name'],
                        'root_path' => rtrim($input['root_path'], '/'),
                        'is_enabled' => true,
                    ]
                );

                $patterns = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', $input['exclusions'] ?? '')
                )));

                $project->syncExclusions($patterns);
            }

            $server->update(['setup_step' => Server::STEP_COMPLETE]);
        });

        return redirect()->route('servers.show', $server)->with('success', 'Настройка завершена');
    }

    private function guardWizardStep(Server $server, int $step): ?RedirectResponse
    {
        if ($step === 1) {
            return null;
        }

        if ($server->isWizardComplete()) {
            return redirect()->route('servers.show', $server);
        }

        if (! $server->canAccessWizardStep($step)) {
            return redirect()
                ->route($server->wizardStepRouteName($server->maxAccessibleWizardStep()))
                ->with('error', 'Сначала завершите предыдущие шаги.');
        }

        return null;
    }
}
