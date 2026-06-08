# Backaper Panel (Laravel)

Веб-панель: пошаговая настройка серверов, MODX-проектов, restic/rclone.

## Мастер настройки (4 шага)

| Шаг | Что делает |
|-----|------------|
| **1. SSH** | Имя, host, user, port. Ключ генерируется автоматически. |
| **2. Конфиги** | Публичный ключ → `authorized_keys`. Restic/rclone. Кнопка «Найти конфиги» → `modx_configs`. |
| **3. Базы** | «Извлечь базы» → парсинг config.inc → таблица `project_databases`. |
| **4. Проекты** | Путь к сайту, исключения → `projects` + `project_exclusions`. |

## Таблицы

- `servers` — SSH, restic, шаг мастера (`setup_step`)
- `modx_configs` — найденные `core/config/*.inc.php`
- `project_databases` — credentials из конфигов
- `projects` — финальные проекты (путь, связь с config и БД)
- `project_exclusions` — правила исключений restic
- `backup_runs` — логи бэкапов

## Запуск

```bash
cd panel
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

## После мастера

1. **«Установить restic»** — по SSH `install.sh`: restic + rclone + `restic init`.
2. **«Бэкап»** (когда есть проекты с путём и БД):
   - **restic** — snapshot файлов с исключениями (репозиторий `restic-repo/{server}` на облаке)
   - **rclone** — `backaper/{server}/databases/{db}/{дата}.sql.gz`

### Artisan

```bash
php artisan backaper:setup 1
php artisan backaper:setup --all
php artisan backaper:backup 1
php artisan backaper:backup --all
```

### Shell (без UI)

```bash
export BACKAPER_HOST=deploy@server.example.com
export BACKAPER_RESTIC_PASSWORD='...'
export BACKAPER_RCLONE_TOKEN='{"access_token":"..."}'
bash ../scripts/backaper-remote-install.sh
```

## Безопасность

Панель без авторизации — только локально/VPN. SQLite содержит ключи и пароли БД.
