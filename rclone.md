# Rclone: установка и подключение к Yandex Disk

В типичном стеке вместе с **restic** и облаком Yandex Disk. Краткая шпаргалка: установка в Linux, настройка remote через OAuth, проверка и базовые команды.

---

## Что нужно

- Linux-сервер или ПК с `bash` (или Windows — см. ниже)
- Аккаунт Yandex и доступ в интернет

---

## Установка rclone (Linux, без root)

1. Скачать и распаковать ([релизы](https://rclone.org/downloads/)):

```bash
curl -O https://downloads.rclone.org/rclone-current-linux-amd64.zip
unzip rclone-current-linux-amd64.zip
cd rclone-*-linux-amd64
chmod +x rclone
```

2. Положить в `~/bin` и добавить в `PATH`:

```bash
mkdir -p ~/bin
mv rclone ~/bin/rclone
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
rclone version
```

Команды выше выполняйте из каталога с распакованным бинарником (`rclone-*-linux-amd64`), чтобы `mv rclone` подхватил файл.

**Windows:** установщик или архив с [официального сайта](https://rclone.org/downloads/), затем добавьте каталог с `rclone.exe` в переменную среды `PATH` (или положите `rclone.exe` в каталог, который уже в `PATH`). Дальше те же шаги `rclone config`.

---

## Настройка remote (`rclone config`)

```bash
rclone config
```

| Шаг | Действие |
|-----|----------|
| Новый remote | `n` |
| Имя | например `yandex` |
| Тип хранилища | `yandex` |
| Client ID | Enter (пусто) |
| Client Secret | Enter (пусто) |
| Advanced config | `n` |

**Браузер на этой машине:** `Use web browser?` → `y` → войти в Yandex.

**Без браузера (сервер / SSH):** `Use web browser?` → `n`. На другой машине с браузером:

```bash
rclone authorize "yandex"
```

Скопировать JSON-токен и вставить в запрос `config_token>`.

Финал: `Keep this remote?` → `y`, выход из мастера → `q`.

---

## Проверка

```bash
rclone ls yandex:
rclone lsd yandex:
```

---

## Базовые команды

| Задача | Команда |
|--------|---------|
| Загрузить файл | `rclone copy file.txt yandex:` |
| Синхронизировать папку на диск | `rclone sync ./folder yandex:backup` |
| Скачать с диска | `rclone copy yandex:backup ./backup` |

---

## Безопасность

- Не публиковать OAuth-токены.
- При утечке — отозвать доступ в настройках аккаунта Yandex и заново пройти авторизацию.

---

## На сервере (по желанию)

- Доступ как к файловой системе: `rclone mount`
- Регулярные копии: `cron` или планировщик ОС
