# Restic: установка и бэкап домашней директории через rclone

Хранилище репозитория — облако через уже настроенный **rclone** (см. `Rclone.md`). Ниже: установка бинарника, переменные окружения, `init`, тестовый бэкап `~/`.

---

## Что нужно

- Linux-сервер или ПК с `bash`
- В `PATH` доступны `restic` и `rclone`
- Remote в rclone с именем, например **`yandex`** (или своё имя — подставляйте ниже вместо `yandex`)

---

## Установка restic (Linux, без root)

1. Скачать и распаковать бинарник ([релизы](https://github.com/restic/restic/releases)):

```bash
wget https://github.com/restic/restic/releases/download/v0.16.4/restic_0.16.4_linux_amd64.bz2
bunzip2 restic_0.16.4_linux_amd64.bz2
chmod +x restic_0.16.4_linux_amd64
```

2. Положить в `~/bin` и добавить в `PATH`:

```bash
mkdir -p ~/bin
mv restic_0.16.4_linux_amd64 ~/bin/restic
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
restic version
```

---

## Репозиторий через rclone

Путь на облаке — произвольная папка под репозиторий restic (она создастся при `init`). Пример: каталог `restic-repo` в корне Яндекс.Диска.

Формат URL:

```text
rclone:ИМЯ_REMOTE:ПУТЬ_НА_ДИСКЕ
```

Пример для remote `yandex` и папки `restic-repo`:

```bash
export RESTIC_REPOSITORY=rclone:yandex:restic-repo
```

Пароль шифрования репозитория (храните надёжно, без него восстановление невозможно):

```bash
export RESTIC_PASSWORD='ваш_надёжный_пароль'
```

Чтобы не вводить каждый раз, те же строки можно добавить в `~/.bashrc` (или использовать файл пароля по документации restic — по желанию).

---

## Первый запуск: создание репозитория

Зачем нужен `restic init`:

- Создаёт структуру репозитория в выбранном backend (`rclone:yandex:...`).
- Инициализирует шифрование и проверяет пароль `RESTIC_PASSWORD`.
- Без `init` команда `restic backup` не сможет писать snapshot'ы.

Важно: делать **только один раз** для нового пути `RESTIC_REPOSITORY`.

Если запускать повторно на уже созданном репозитории, будет ошибка "repository master key and config already initialized" — это нормально.

Один раз на новом `RESTIC_REPOSITORY`:

```bash
restic init
```

---

## Как сделать бэкап (первый и последующие)

1. Убедиться, что переменные окружения заданы в текущей сессии:

```bash
echo "$RESTIC_REPOSITORY"
echo "$RESTIC_PASSWORD" | wc -c
```

2. Первый тестовый бэкап домашней директории:

```bash
restic backup ~
```

3. Проверить, что snapshot создался:

```bash
restic snapshots
```

4. Все следующие бэкапы запускаются той же командой:

```bash
restic backup ~
```

Restic делает дедупликацию: повторный бэкап сохраняет только изменения, а не полную копию заново.

---

## Исключения (по желанию)

Чтобы не тащить кэши и тяжёлые артефакты:

```bash
restic backup ~ \
  --exclude '.cache' \
  --exclude '**/node_modules' \
  --exclude '**/tmp'
```

Подстроите `--exclude` под свои каталоги.

---

## Восстановление (restore)

1. Посмотреть доступные снимки:

```bash
restic snapshots
```

2. Полностью восстановить снимок в отдельную папку:

```bash
mkdir -p ~/restore-test
restic restore latest --target ~/restore-test
```

3. Восстановить конкретный snapshot по ID:

```bash
restic restore <SNAPSHOT_ID> --target ~/restore-test
```

4. Восстановить только один путь из снимка:

```bash
restic restore latest --target ~/restore-test --include /home/USER/Documents
```

После восстановления проверьте файлы в целевой папке и только потом переносите их в рабочие директории.

---

## Краткая шпаргалка команд

| Действие | Команда |
|----------|---------|
| Бэкап `~/` | `restic backup ~` |
| Список снимков | `restic snapshots` |
| Полное восстановление | `restic restore latest --target ~/restore-test` |
| Выборочное восстановление | `restic restore latest --target ~/restore-test --include /путь` |
| Проверка целостности | `restic check` |

Подробнее про backend **rclone**: [restic — rclone](https://restic.readthedocs.io/en/stable/030_preparing_a_new_repo.html#other-services-via-rclone).
