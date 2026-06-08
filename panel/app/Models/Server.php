<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    public const STEP_SSH = 1;

    public const STEP_SETTINGS = 2;

    public const STEP_DATABASES = 3;

    public const STEP_PROJECTS = 4;

    public const STEP_COMPLETE = 5;

    protected $fillable = [
        'name',
        'host',
        'ssh_port',
        'ssh_user',
        'ssh_auth_type',
        'ssh_password',
        'ssh_private_key',
        'ssh_public_key',
        'setup_step',
        'restic_password',
        'rclone_remote',
        'rclone_token',
        'restic_repo_slug',
        'is_setup_complete',
        'setup_log',
        'last_discovered_at',
        'config_discovery_status',
        'config_discovery_started_at',
        'config_discovery_error',
        'config_discovery_pid',
        'config_discovery_remote_pid',
    ];

    public const DISCOVERY_IDLE = 'idle';

    public const DISCOVERY_RUNNING = 'running';

    public const DISCOVERY_COMPLETED = 'completed';

    public const DISCOVERY_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'is_setup_complete' => 'boolean',
            'last_discovered_at' => 'datetime',
            'config_discovery_started_at' => 'datetime',
        ];
    }

    public function isDiscoveryRunning(): bool
    {
        if ($this->config_discovery_status !== self::DISCOVERY_RUNNING) {
            return false;
        }

        if ($this->config_discovery_started_at?->lt(now()->subMinutes(60))) {
            return false;
        }

        return true;
    }

    public function markDiscoveryRunning(): void
    {
        $this->update([
            'config_discovery_status' => self::DISCOVERY_RUNNING,
            'config_discovery_started_at' => now(),
            'config_discovery_error' => null,
            'config_discovery_pid' => null,
            'config_discovery_remote_pid' => null,
        ]);
    }

    public function markDiscoveryCompleted(int $found): void
    {
        $this->update([
            'config_discovery_status' => self::DISCOVERY_COMPLETED,
            'config_discovery_error' => $found > 0
                ? null
                : 'Конфиги MODX не найдены. Проверены пути из php-fpm/nginx, ~/web и домашняя директория.',
            'last_discovered_at' => now(),
            'config_discovery_pid' => null,
            'config_discovery_remote_pid' => null,
        ]);
    }

    public function markDiscoveryFailed(string $message): void
    {
        $this->update([
            'config_discovery_status' => self::DISCOVERY_FAILED,
            'config_discovery_error' => $message,
            'config_discovery_pid' => null,
            'config_discovery_remote_pid' => null,
        ]);
    }

    public function resetStaleDiscovery(): void
    {
        if ($this->config_discovery_status === self::DISCOVERY_RUNNING
            && $this->config_discovery_started_at?->lt(now()->subMinutes(60))) {
            $this->update([
                'config_discovery_status' => self::DISCOVERY_FAILED,
                'config_discovery_error' => 'Поиск прерван (таймаут). Запустите снова.',
                'config_discovery_pid' => null,
                'config_discovery_remote_pid' => null,
            ]);
        }
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function modxConfigs(): HasMany
    {
        return $this->hasMany(ModxConfig::class);
    }

    public function projectDatabases(): HasMany
    {
        return $this->hasMany(ProjectDatabase::class);
    }

    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    public function isWizardComplete(): bool
    {
        return $this->setup_step >= self::STEP_COMPLETE;
    }

    public function wizardRoute(): string
    {
        return $this->wizardStepRouteName(max(2, min(4, $this->setup_step)));
    }

    public function wizardStepRouteName(int $step): string
    {
        return match ($step) {
            1 => 'servers.wizard.step1',
            2 => 'servers.wizard.step2',
            3 => 'servers.wizard.step3',
            4 => 'servers.wizard.step4',
            default => 'servers.wizard.step2',
        };
    }

    public function maxAccessibleWizardStep(): int
    {
        $max = max(2, min(4, $this->setup_step));

        if ($this->modxConfigs()->exists()) {
            $max = max($max, 3);
        }
        if ($this->projectDatabases()->exists()) {
            $max = max($max, 4);
        }

        return $max;
    }

    public function canAccessWizardStep(int $step): bool
    {
        if ($step < 1 || $step > 4) {
            return false;
        }

        if ($step === 1) {
            return true;
        }

        if ($this->isWizardComplete()) {
            return false;
        }

        return $step <= $this->maxAccessibleWizardStep();
    }

    public function resticRepository(): string
    {
        return 'rclone:'.$this->rclone_remote.':restic-repo/'.$this->repoSlug();
    }

    public function cloudPrefix(): string
    {
        return 'backaper/'.$this->repoSlug();
    }

    public function repoSlug(): string
    {
        $slug = $this->restic_repo_slug ?: $this->name;
        $slug = preg_replace('/[^a-zA-Z0-9._-]/', '-', $slug);

        return $slug ?: 'server';
    }

    public const AUTH_KEY = 'key';

    public const AUTH_PASSWORD = 'password';

    public function usesPasswordAuth(): bool
    {
        return $this->ssh_auth_type === self::AUTH_PASSWORD;
    }

    public function usesKeyAuth(): bool
    {
        return ! $this->usesPasswordAuth();
    }

    public function readyForRemoteSetup(): bool
    {
        return $this->isWizardComplete()
            && ! empty($this->restic_password)
            && ! empty($this->rclone_token);
    }

    public function readyForBackup(): bool
    {
        if (! $this->isWizardComplete() || ! $this->is_setup_complete) {
            return false;
        }

        return $this->projects()
            ->where('is_enabled', true)
            ->whereNotNull('root_path')
            ->where('root_path', '!=', '')
            ->whereHas('database')
            ->exists();
    }

    public function storageSlug(string $name): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        return substr($slug ?: 'item', 0, 120);
    }
}
