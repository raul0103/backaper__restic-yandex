# Restic на каждом сервере + удалённый запуск по SSH

Схема: на **каждом** сервере установлены `restic` и `rclone`, данные уходят **напрямую в облако** с этого сервера. С **другой машины** (ноутбук, VPS, домашний ПК) вы только **подключаетесь по SSH** и запускаете бэкап — или вешаете cron на этой машине-оркестраторе.

Перед бэкапом на сервере создаётся **дамп БД в корень проекта** (или в подпапку `backups/`), затем `restic backup` забирает файлы проекта вместе с дампом.

Базовая настройка restic/rclone: [restic.md](restic.md), [rclone.md](rclone.md).

---

## Схема

```
                    SSH (запуск скрипта)
 control-машина ─────────────────────────► server1 ──► restic ──► Yandex Disk
 (только ssh)     ─────────────────────────► server2 ──► restic ──► Yandex Disk
                  ─────────────────────────► server3 ──► restic ──► Yandex Disk
```

На control-машине **не нужны** restic и rclone (по желанию — только для просмотра snapshot через SSH).

На каждом сервере:

1. Дамп БД → файл в каталоге проекта.
2. `restic backup /var/www/project` → облако.

---

## Плюсы и минусы vs «всё на одной машине через rsync»

| | Restic на каждом сервере | Rsync на одну машину |
|--|--------------------------|----------------------|
| Диск control-машины | Не нужен под копии | Нужен staging |
| Трафик | Сервер → облако напрямую | Сервер → control → облако |
| Настройка | На каждом сервере restic + rclone | Только на control |
| Дамп БД | Логично на том же сервере, где БД | Дамп на сервере, потом rsync |
| Управление | Один SSH-скрипт с control | Один скрипт на control |

Для вашего случая (дамп БД в проект + много серверов) эта схема **удобнее**.

---

## Однократная настройка на каждом сервере

### 1. Установить restic и rclone

См. [restic.md](restic.md) и [rclone.md](rclone.md).

### 2. rclone → Yandex Disk

На **каждом** сервере один раз `rclone config` (remote `yandex`).

OAuth без браузера на сервере: на машине с браузером `rclone authorize "yandex"`, токен вставить на сервере.

Можно **скопировать** готовый `~/.config/rclone/rclone.conf` с уже настроенного сервера (один аккаунт Yandex — один конфиг).

### 3. Переменные restic (уникальный путь на сервер)

Файл на сервере `~/backaper/restic.env` (права `600`):

```bash
export RESTIC_REPOSITORY=rclone:yandex:restic-repo/server1
export RESTIC_PASSWORD='общий_или_свой_пароль'
```

Имя в пути замените: `server1` → hostname или своё имя (`shop-prod`, `blog`).

**Один пароль** на все репозитории — проще восстановление. **Разные пароли** — изоляция, но сложнее хранить.

### 4. Один раз init на каждом сервере

```bash
. ~/backaper/restic.env
restic init
```

Повторный `init` на том же пути даст ошибку — это нормально.

### 5. SSH-ключ с control-машины

На control-машине ключ, на каждом сервере — в `authorized_keys` (как в [central-backup.md](central-backup.md), шаг 1).

---

## Дамп БД в корень проекта

Дамп делается **на сервере**, где крутится БД, **до** `restic backup`. Restic не подключается к MySQL/PostgreSQL сам.

### Куда класть файл

Рекомендуется подпапка, а не буквально «в корень»:

```text
/var/www/myproject/backups/db.sql
```

или с датой (удобно для отката):

```text
/var/www/myproject/backups/db-2026-06-05.sql
```

В `restic backup` указываете **родительский каталог проекта** — дамп попадёт в snapshot.

### MySQL / MariaDB

Учётные данные — в `~/.my.cnf` (права `600`), не в скрипте:

```ini
[client]
user=backup_user
password=секрет
host=localhost
```

Скрипт на сервере `~/backaper/dump-mysql.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${1:?Укажите корень проекта, например /var/www/site}"
DB_NAME="${2:?Укажите имя БД}"
BACKUP_DIR="$PROJECT_ROOT/backups"
OUT="$BACKUP_DIR/db.sql"

mkdir -p "$BACKUP_DIR"
mysqldump --single-transaction --routines --triggers "$DB_NAME" > "$OUT"
# опционально: оставить только последний дамп
find "$BACKUP_DIR" -name 'db-*.sql' -mtime +7 -delete 2>/dev/null || true
```

Пример вызова:

```bash
~/backaper/dump-mysql.sh /var/www/site mydb
```

### PostgreSQL

Файл `~/.pgpass` (права `600`):

```text
localhost:5432:mydb:backup_user:секрет
```

Скрипт `~/backaper/dump-pgsql.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${1:?}"
DB_NAME="${2:?}"
BACKUP_DIR="$PROJECT_ROOT/backups"
OUT="$BACKUP_DIR/db.sql"

mkdir -p "$BACKUP_DIR"
pg_dump -h localhost -U backup_user -d "$DB_NAME" -F p -f "$OUT"
```

Создайте пользователя БД только с правами на чтение/дамп (не root приложения).

### Несколько БД или несколько проектов

В конфиге сервера можно указать несколько пар «проект + БД» — см. `servers.conf` ниже.

---

## Локальный скрипт бэкапа на сервере

На **каждом** сервере `~/backaper/backup.sh` — его и вызывает control-машина по SSH.

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKAPER="$HOME/backaper"
# shellcheck source=/dev/null
. "$BACKAPER/restic.env"

PROJECT_ROOT="${PROJECT_ROOT:-/var/www/site}"
DB_TYPE="${DB_TYPE:-mysql}"          # mysql | pgsql | none
DB_NAME="${DB_NAME:-mydb}"
LOG="$BACKAPER/logs/backup-$(date +%Y%m%d-%H%M%S).log"

mkdir -p "$BACKAPER/logs"

exec > >(tee -a "$LOG") 2>&1
echo "=== backup $(hostname) $(date -Is) ==="

if [[ "$DB_TYPE" == "mysql" && -n "$DB_NAME" ]]; then
  "$BACKAPER/dump-mysql.sh" "$PROJECT_ROOT" "$DB_NAME"
elif [[ "$DB_TYPE" == "pgsql" && -n "$DB_NAME" ]]; then
  "$BACKAPER/dump-pgsql.sh" "$PROJECT_ROOT" "$DB_NAME"
fi

restic backup "$PROJECT_ROOT" \
  --exclude '.cache' \
  --exclude '**/node_modules' \
  --exclude '**/tmp'

restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune

echo "=== done ==="
```

Переменные `PROJECT_ROOT`, `DB_TYPE`, `DB_NAME` можно задать в `~/backaper/server.env` на каждом хосте:

```bash
export PROJECT_ROOT=/var/www/site
export DB_TYPE=mysql
export DB_NAME=production_db
```

И в начале `backup.sh` добавить: `. "$BACKAPER/server.env"`.

```bash
chmod +x ~/backaper/*.sh
chmod 600 ~/backaper/restic.env ~/backaper/server.env
```

Проверка **на сервере**:

```bash
~/backaper/backup.sh
restic snapshots
```

---

## Control-машина: список серверов

`~/backaper/servers.conf` на **control-машине** (не на серверах):

```text
# SSH_HOST — имя из ~/.ssh/config или user@host
server1
server2
server3
```

---

## Control-машина: запуск всех бэкапов

`~/backaper/run-all.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

CONF="${HOME}/backaper/servers.conf"
LOG_DIR="${HOME}/backaper/logs"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/backaper}"

mkdir -p "$LOG_DIR"
MAIN_LOG="$LOG_DIR/run-all-$(date +%Y%m%d-%H%M%S).log"

exec > >(tee -a "$MAIN_LOG") 2>&1
echo "=== run-all $(date -Is) ==="

while IFS= read -r host || [[ -n "$host" ]]; do
  host="${host%%#*}"
  host="$(echo "$host" | xargs)"
  [[ -z "$host" ]] && continue

  echo "--- $host ---"
  if ssh -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=30 "$host" \
      '$HOME/backaper/backup.sh'; then
    echo "OK: $host"
  else
    echo "FAIL: $host" >&2
  fi
done < "$CONF"

echo "=== finished ==="
```

```bash
chmod +x ~/backaper/run-all.sh
~/backaper/run-all.sh
```

### Запуск одного сервера

```bash
ssh server1 '~/backaper/backup.sh'
```

### Просмотр snapshot с control-машины (без локального restic)

```bash
ssh server1 '. ~/backaper/restic.env && restic snapshots'
```

---

## Cron на control-машине

Бэкапы идут **только когда control-машина онлайн** и cron срабатывает (ноутбук — плохой вариант; лучше маленький VPS или домашний сервер 24/7).

```cron
0 3 * * * /home/user/backaper/run-all.sh
```

### Альтернатива: cron на каждом сервере

Если control-машина не всегда доступна — тот же `~/backaper/backup.sh` в crontab **на сервере**:

```cron
0 3 * * * /home/deploy/backaper/backup.sh
```

Control-машина тогда нужна только для ручного запуска и проверок.

---

## Добавление нового сервера

1. Установить restic + rclone, `restic init` с уникальным `RESTIC_REPOSITORY`.
2. Скопировать `~/backaper/` (скрипты, `server.env`, при необходимости `rclone.conf`).
3. Настроить дамп БД и `PROJECT_ROOT`.
4. Добавить хост в `servers.conf` на control-машине.
5. `ssh newserver '~/backaper/backup.sh'`.

---

## Восстановление

На **нужном сервере** (или после развёртывания restic там):

```bash
. ~/backaper/restic.env
restic snapshots
mkdir -p ~/restore-test
restic restore latest --target ~/restore-test
```

Файлы проекта и `backups/db.sql` будут в `~/restore-test/...`.

Импорт MySQL:

```bash
mysql -u user -p mydb < ~/restore-test/var/www/site/backups/db.sql
```

---

## Безопасность

| Секрет | Где хранить |
|--------|-------------|
| `RESTIC_PASSWORD` | `~/backaper/restic.env` на сервере, `600` |
| Пароль БД | `~/.my.cnf` / `~/.pgpass`, не в git |
| rclone OAuth | `~/.config/rclone/rclone.conf` |
| SSH-ключ control | только на control-машине |

Пользователь для SSH может быть без sudo, если есть чтение `/var/www` и запуск mysqldump (отдельный `backup_user` в MySQL).

---

## Структура файлов

**На каждом сервере:**

```text
~/backaper/
├── restic.env      # RESTIC_REPOSITORY, RESTIC_PASSWORD
├── server.env      # PROJECT_ROOT, DB_TYPE, DB_NAME
├── dump-mysql.sh
├── dump-pgsql.sh
├── backup.sh       # дамп + restic backup
└── logs/
```

**На control-машине:**

```text
~/backaper/
├── servers.conf    # список SSH-хостов
├── run-all.sh
└── logs/
```

---

## Краткий чек-лист

```bash
# control-машина
ssh server1 '~/backaper/backup.sh'
~/backaper/run-all.sh

# проверка на сервере
ssh server1 '. ~/backaper/restic.env && restic snapshots'

# облако (на любом сервере с rclone)
ssh server1 'rclone ls yandex:restic-repo/server1'
```
