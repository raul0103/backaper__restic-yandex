<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BackupOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function edit(Project $project): View
    {
        $project->load(['database', 'modxConfig', 'exclusionRules']);

        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'root_path' => ['required', 'string', 'max:1024'],
            'exclusions' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $patterns = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', $data['exclusions'] ?? '')
        )));

        $project->update([
            'name' => $data['name'],
            'root_path' => rtrim($data['root_path'], '/'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        $project->syncExclusions($patterns);

        return redirect()->route('servers.show', $project->server_id)->with('success', 'Проект обновлён');
    }

    public function backup(Project $project, BackupOrchestrator $orchestrator): RedirectResponse
    {
        try {
            $run = $orchestrator->startProjectBackup($project);
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
}
