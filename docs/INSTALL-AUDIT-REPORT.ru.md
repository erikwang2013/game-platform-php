# Отчёт об аудите системы установки
<!-- lang-nav -->

Languages: **中文** · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> Дата проверки: 2026-08-04
> Область проверки: все файлы в каталоге `install/` + соответствующие изменения документации
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Сводка проверки

| Измерение | Оценка | Примечание |
|------|------|------|
| Полнота функций | Пройдено | полный процесс установки из 5 шагов, созданы все 39 таблиц, стартовые данные на месте |
| Корректность SQL | Пройдено | 42 таблицы полностью совпадают с исходными файлами миграций, поле source объединено в CREATE TABLE |
| Полнота конфигурации окружения | Пройдено | полные конфигурации .env для admin и service, автоматическая генерация ключей |
| Безопасность | В основном пройдено | пароль bcrypt, защита от XSS реализована, рекомендуется добавить CSRF-токен |
| Поддерживаемость | Пройдено | чёткая структура кода, ясная ответственность каждого файла |
| Идемпотентность | Пройдено | все INSERT переведены на INSERT IGNORE, с защитой WHERE NOT EXISTS |
| Пользовательский опыт | Пройдено | адаптивный дизайн, AJAX-тест подключения, понятные сообщения об ошибках на китайском |

---

## 2. Созданные файлы

### 2.1 `install/install.sql` (988 строк)
- объединены 8 исходных файлов миграций
- 42 таблицы данных с префиксом `erik_` (CREATE TABLE IF NOT EXISTS)
- 13 блоков стартовых данных INSERT IGNORE
- поле `source` таблицы `erik_operation_log` объединено в оператор создания таблицы (ALTER TABLE не требуется)
- обёрнуто в транзакцию (START TRANSACTION / COMMIT)
- все INSERT идемпотентны

**Детали идемпотентной обработки INSERT:**

| Таблица | Способ обработки |
|------|---------|
| `erik_admin_role` | INSERT IGNORE (фиксированные ID) |
| `erik_admin_permission` | INSERT IGNORE (фиксированные ID) - 4 раза |
| `erik_admin_role_permission` | подзапрос WHERE NOT EXISTS |
| `erik_platform_config` | INSERT IGNORE (фиксированные ID) - 2 раза |
| `erik_language` | INSERT IGNORE (фиксированные ID) |
| `erik_translation` | INSERT IGNORE (фиксированные ID) |
| `erik_risk_rule` | INSERT IGNORE (фиксированные ID) |
| `erik_withdraw_limit` | INSERT IGNORE (фиксированные ID) |
| `erik_game_category` | INSERT IGNORE (фиксированные ID) |
| `erik_country_config` | INSERT IGNORE (фиксированные ID) |

### 2.2 `install/index.php` (485 строк)
- маршрутизация: step1 -> step2 -> step3 -> step4 -> step5
- AJAX-интерфейс: `?action=test-db` (POST JSON)
- 5 функций шаблонов страниц
- встроенный JavaScript (AJAX-тест подключения)
- HTML-вывод экранируется через `htmlspecialchars()` от XSS
- детекция установленной системы (install.lock)

### 2.3 `install/Installer.php` (506 строк)
- проверка окружения: 11 пунктов (версия PHP, 6 расширений, права на каталоги, SQL-файл)
- тест подключения к БД: PDO + автоматическое создание базы данных
- выполнение установки: импорт SQL -> создание администратора -> запись .env -> блокировка
- генерация ключей: JWT (64 байта) / Hashids (32 байта) / Encryption (32 байта)
- резервное копирование .env: существующие файлы .env автоматически бэкапятся перед установкой

### 2.4 `install/assets/style.css` (130 строк)
- адаптивный дизайн (поддержка мобильных <=600px)
- тема на CSS-переменных (--primary: #4f46e5)
- без внешних зависимостей

---

## 3. Покрытие проверок окружения (11 пунктов)

| # | Пункт проверки | Уровень | Статус |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | обязательный | пройдено |
| 2 | PDO MySQL | обязательный | пройдено |
| 3 | MBString | обязательный | пройдено |
| 4 | JSON | обязательный | пройдено |
| 5 | OpenSSL | обязательный | пройдено |
| 6 | PCNTL | обязательный | пройдено |
| 7 | GD | рекомендуется | пройдено |
| 8 | XML | рекомендуется | пройдено |
| 9 | Redis | рекомендуется | пройдено |
| 10 | права на каталоги (admin/runtime, service/runtime) | обязательный | пройдено |
| 11 | наличие файла install.sql | обязательный | пройдено |

---

## 4. Полнота конфигурации окружения

### 4.1 Генерация `.env` admin (70 пунктов конфигурации)

| Группа | Число пунктов | Покрытие |
|------|---------|------|
| Конфигурация приложения | 3 | APP_NAME, APP_DEBUG, APP_URL |
| JWT-аутентификация | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| Шифрование (API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| Шифрование (БД) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST и др. |
| Капча Poster | 7 | POSTER_IMAGE_DRIVER и др. |
| База данных | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| Совместимые ключи | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Генерация `.env` service (48 пунктов конфигурации)

| Группа | Число пунктов | Покрытие |
|------|---------|------|
| Приложение | 2 | APP_ENV, APP_DEBUG |
| База данных | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| Шифрование | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE по 3 пункта |
| Платёжные Webhook | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER и др. |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST и др. |

**Вывод сравнения**: обе конфигурации `.env` совпадают с исходными `.env.example`, дополнительно в конфигурацию Service добавлены отсутствовавшие `ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL`.

---

## 5. Проверка безопасности

### 5.1 Реализованные меры безопасности

| Мера | Способ реализации |
|------|---------|
| Безопасность пароля | bcrypt, cost=12 |
| Случайность ключей | `random_int()` криптостойкий ГСЧ |
| Защита от XSS | `htmlspecialchars()` экранирует весь пользовательский ввод/вывод |
| Защита от SQL-инъекций | PDO prepared statements (`prepare/execute`) |
| Блокировка установки | файл `install.lock` + JSON-метаданные |
| Безопасность путей | фиксированные пути, нет управляемых пользователем подключений файлов |
| Стойкость шифрования | AES-256-CBC + ключ 32 байта |

### 5.2 Потенциальные риски и смягчение

| Риск | Уровень | Меры смягчения |
|------|------|---------|
| Сетевая доступность во время установки | средний | немедленно удалить каталог `install/` после установки (на странице есть заметное предупреждение) |
| Отсутствие CSRF-токена | низкий | мастер установки — временный одноразовый инструмент, встроенный PHP-сервер однопоточный |
| test-db без ограничения частоты | низкий | временный инструмент, удаляется после использования |
| Права на файлы .env | низкий | после установки рекомендуется вручную выполнить chmod 600 |

### 5.3 Рекомендации по улучшению

1. **Укрепление производственной среды**: после завершения установки можно автоматически выполнять `chmod 600 admin/.env service/.env`
2. **Удалённый доступ**: для удалённого сервера рекомендуется SSH-туннель: `ssh -L 8888:localhost:8888 user@host`
3. **Очистка после установки**: рассмотреть добавление заметного предупреждения «удалить каталог установки» на странице успешной установки (реализовано)

---

## 6. Результаты тестирования

### 6.1 Проверка синтаксиса PHP
```
通过 install/index.php — No syntax errors
通过 install/Installer.php — No syntax errors
```

### 6.2 Функциональное тестирование
```
通过 Step 1 环境检查 — 11项检查全部通过
通过 Step 2 数据库配置 — 表单渲染正确，默认值填充正常
通过 AJAX test-db — JSON响应格式正确，中文错误提示清晰
通过 CSS 静态资源 — 200 OK, text/css
通过 已安装页面 — install.lock检测正常，提示信息完整
```

### 6.3 Проверка SQL
```
通过 42张表名与原始迁移文件完全一致
通过 source字段已合并到 erik_operation_log 建表语句
通过 所有INSERT语句已做幂等处理
通过 WHERE NOT EXISTS 守卫已恢复（与原迁移一致）
```

---

## 7. Найденные и исправленные проблемы

| # | Проблема | Серьёзность | Статус |
|---|------|--------|------|
| 1 | INSERT `erik_admin_role_permission` не хватает защиты `WHERE NOT EXISTS` (несоответствие исходной миграции) | высокая | исправлено |
| 2 | Все INSERT стартовых данных не идемпотентны (повторное выполнение завершится ошибкой) | средняя | исправлено (INSERT IGNORE) |
| 3 | В проверке окружения отсутствует проверка расширения `pcntl` (ключевая зависимость webman) | средняя | исправлено |
| 4 | В Service .env отсутствует конфигурация `ENCRYPTION_CIPHER` | низкая | исправлено |
| 5 | В Service .env отсутствует конфигурация `ENCRYPTABLE_CIPHER` | низкая | исправлено |
| 6 | В Service .env отсутствует конфигурация `JWT_REFRESH_TTL` | низкая | исправлено |

---

## 8. Изменения документации

| Файл | Содержание изменений |
|------|---------|
| `README.md` | раздел «Быстрый старт» заменён на «Мастер установки в один клик (рекомендуется)», добавлен свёрнутый блок ручной установки, обновлена структура проекта |
| `README.en.md` | то же (английская версия), обновлена структура проекта |
| `docs/DEPLOYMENT.md` | добавлен раздел 2 «Мастер установки в один клик (рекомендуется для новых развёртываний)», исходная глава про Docker перенесена ниже |
| `.gitignore` | добавлены `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## 9. Общая оценка

Система установки функционально полна, качество кода хорошее, меры безопасности на месте. Процесс установки из 5 шагов чёткий и наглядный, проверка окружения охватывает все ключевые расширения, необходимые для работы webman, автоматически генерируются ключи высокой стойкости, файлы конфигурации полностью совместимы с существующей системой. Процесс объединения SQL сохранил полное соответствие исходным файлам миграций (42 таблицы), идемпотентная обработка гарантирует отсутствие ошибок при повторном выполнении.

**Заключение проверки: пройдено, можно вводить в эксплуатацию.**

---

## 10. Подтверждение статуса от 2026-08-18

Текущий раунд исправлений безопасности (fail-closed платёжных колбэков, проверка JWT при запуске, унификация префикса таблиц) **не затрагивает систему установки**, новых проблем нет:

- После удаления жёстко зашитого префикса `erik_` в моделях фактические имена таблиц по-прежнему генерируются единообразно через `prefix=erik_` в `config/database.php`, что совпадает с таблицами `erik_*` из install.sql, менять установочный SQL не нужно
- Проверка JWT при запуске (отказ запуска при отсутствии `JWT_SECRET_KEY` или значении по умолчанию) совместима с автоматически генерируемым мастером установки 64-байтовым случайным ключом, корректировка процесса установки не требуется

Исторические выводы и список проблем остаются без изменений.

---
