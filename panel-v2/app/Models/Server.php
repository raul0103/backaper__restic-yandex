<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    public const KIND_VPS = 'vps';

    public const KIND_HOSTING = 'hosting';

    public const STEP_CONNECT = 1;

    public const STEP_INSTALL = 2;

    /** После установки restic — настройка завершена (бэкапы только CLI). */
    public const STEP_COMPLETE = 3;

    /** @deprecated legacy wizard step; treated as complete */
    public const STEP_CONTENT = 3;

    public const DEFAULT_RESTIC_PASSWORD = 'backaper658715';

    public const AUTH_PASSWORD = 'password';

    public const AUTH_KEY = 'key';

    /** Исключения для полного бэкапа файлов (restic). Хостинг: + служебные каталоги Beget. */
    public const DEFAULT_EXCLUSIONS = [
        'core/cache/**',
        '**/node_modules/**',
        '**/.git/**',
        '**/vendor/**',
        // Beget / shared — нет прав или бесполезно
        '.service',
        '.service/**',
        '.cagefs',
        '.cagefs/**',
        '.cl.selector',
        '.cl.selector/**',
        '.spamassassin',
        '.spamassassin/**',
        '.softaculous',
        '.softaculous/**',
        '.local',
        '.local/**',
        '.cache',
        '.cache/**',
        '.composer',
        '.composer/**',
        '.npm',
        '.npm/**',
        '.config',
        '.config/**',
        'mail',
        'mail/**',
        'tmp',
        'tmp/**',
        '.tmp',
        '.tmp/**',
        '**/lscache/**',
        '**/cgi-bin/**',
        'backaper/tmp/**',
        '.backaper/**',
    ];

    /** Доп. исключения при бэкапе всего VPS (/). */
    public const VPS_SYSTEM_EXCLUSIONS = [
        'dev',
        'proc',
        'sys',
        'run',
        'tmp',
        'var/tmp',
        'var/cache',
        'var/run',
        'mnt',
        'media',
        'lost+found',
        'swapfile',
        '**/node_modules/**',
        '**/.git/**',
        '**/vendor/**',
        'core/cache/**',
    ];

    protected $fillable = [
        'name',
        'kind',
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
    ];

    protected function casts(): array
    {
        return [
            'is_setup_complete' => 'boolean',
            'last_discovered_at' => 'datetime',
        ];
    }

    public function backupPaths(): HasMany
    {
        return $this->hasMany(BackupPath::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(DatabaseCredential::class, 'server_id');
    }

    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(BackupBatchItem::class);
    }

    public function isVps(): bool
    {
        return $this->kind === self::KIND_VPS;
    }

    public function isHosting(): bool
    {
        return $this->kind === self::KIND_HOSTING;
    }

    public function kindLabel(): string
    {
        return $this->isVps() ? 'VPS' : 'Хостинг';
    }

    public function isWizardComplete(): bool
    {
        return $this->is_setup_complete || $this->setup_step >= self::STEP_COMPLETE;
    }

    public function wizardRoute(): string
    {
        return $this->setup_step <= self::STEP_CONNECT
            ? 'servers.wizard.connect'
            : 'servers.wizard.install';
    }

    public function resticRepository(): string
    {
        return 'rclone:'.$this->rclone_remote.':restic-repo/'.$this->repoSlug();
    }

    public function cloudPrefix(): string
    {
        return 'databases/'.$this->repoSlug();
    }

    public function repoSlug(): string
    {
        $slug = $this->restic_repo_slug ?: $this->name;
        $slug = preg_replace('/[^a-zA-Z0-9._-]/', '-', $slug);

        return $slug ?: 'server';
    }

    public function usesPasswordAuth(): bool
    {
        return $this->ssh_auth_type === self::AUTH_PASSWORD;
    }

    public function readyForRemoteSetup(): bool
    {
        return ! empty($this->host)
            && ! empty($this->ssh_user)
            && ! empty($this->restic_password)
            && ! empty($this->rclone_token);
    }

    public function readyForBackup(): bool
    {
        return $this->is_setup_complete;
    }

    public function storageSlug(string $name): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        return substr($slug ?: 'item', 0, 120);
    }

    /** Что бэкапить целиком — без ручного выбора путей. */
    public function fullBackupTarget(): array
    {
        if ($this->isVps()) {
            return ['path' => '/', 'label' => 'Весь сервер (/)'];
        }

        return ['path' => '~', 'label' => 'Hosting home', 'slug' => 'home'];
    }

    /** @return list<string> */
    public function fileExclusions(): array
    {
        return $this->isVps() ? self::VPS_SYSTEM_EXCLUSIONS : self::DEFAULT_EXCLUSIONS;
    }

    /** Создаёт/обновляет единственный путь «всё». */
    public function syncFullBackupPath(): BackupPath
    {
        $target = $this->fullBackupTarget();

        BackupPath::query()
            ->where('server_id', $this->id)
            ->where('path', '!=', $target['path'])
            ->delete();

        return BackupPath::query()->updateOrCreate(
            ['server_id' => $this->id, 'path' => $target['path']],
            ['label' => $target['label'], 'is_enabled' => true]
        );
    }

    /** @deprecated use syncFullBackupPath */
    public function suggestedPaths(): array
    {
        return [$this->fullBackupTarget()];
    }
}
