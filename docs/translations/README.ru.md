# Глобальная игровая агрегационная платформа (Global Game Platform)

## Талисман проекта

<img src="../mascot.svg" width="120" alt="Dicey"/>

**Дайси (Dicey)** — Талисман платформы. Кость символизирует игры и вероятностный геймплей, монета — экономику платформы и мульти-платежные шлюзы, фиолетовый основной цвет перекликается с брендом админки. SVG-файл: `docs/mascot.svg`, бесконечно масштабируется для документации, логотипов и мерча.
<!-- lang-nav -->

Languages: [中文](../../README.md) · [English](README.en.md) · [한국어](README.ko.md) · **Русский** · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Глобальная, интернациональная игровая агрегационная платформа. После регистрации пользователь пополняет счет и обменивает деньги на игровую валюту, играет в игры и зарабатывает игровую валюту, которую можно перевести обратно в кошелек и вывести. Административная панель предоставляет полный набор функций: управление играми, проверка выводов, управление пользователями и управление платежами. Поддерживается переключение языков (английский/китайский).

## Стратегия версий

| Версия | Цель | Статус |
|------|------|------|
| Полная версия | Полный функционал: рейтинги, купоны, категории игр, настройка стран, ES-поиск | Готово |
| Экосистемное расширение | v2.0: подключение игровых провайдеров, тикеты, VIP, достижения, соцсеть, шина событий | Готово |
| v1.3.15-22 (8 версий) | Сверка/расчёты, углублённый риск-контроль, единый кошелёк, движок акций, античит, социальный рост, Adyen/GrabPay | Готово |

## Технологический стек

### Бэкенд
- PHP 8.3+, webman v2 (workerman/webman)
- MySQL 8.0+ (префикс таблиц `game_`, BIGINT неавтоинкрементные первичные ключи)
- Redis (Session / кэш / ограничение частоты запросов)
- ClickHouse (OLAP-аналитика / вычисление вероятностей)
- Elasticsearch (полнотекстовый поиск)
- JWT-аутентификация + RBAC-контроль доступа
- Шифрование данных: AES-256-CBC на транспортном уровне API + AES-128-ECB на уровне хранения в БД

### Фронтенд

Есть два отдельных дерева каталогов фронтенда, **каждое обращается только к бэкенду своей стороны**, без пересечений:

| Дерево каталогов | Назначение | Префикс запроса | Бэкенд | Технологии |
|--------|------|---------|---------|--------|
| `apps/*` | **Платформа игрока C-части** | `/api/v1/...` | service (по умолчанию 8792) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |
| `admin/apps/*` | **Консоль администрирования** | `/admin/v1/...` | admin (по умолчанию 8789) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |

- Адаптивная верстка (Phone / Tablet / Desktop)
- Интернационализация (i18n): английский / упрощенный китайский

### Ключевые компоненты
- `erikwang2013/snowflake-php` — генерация глобально уникальных BIGINT ID
- `erikwang2013/hashids` — шифрование/дешифрование ID на уровне API
- `erikwang2013/jwt-webman` — JWT-аутентификация
- `erikwang2013/encryption` — шифрование чувствительных данных API
- `erikwang2013/encryptable` — шифрование чувствительных полей БД
- `erikwang2013/webman-scout` — синхронизация и поиск в Elasticsearch
- `erikwang2013/season` — флаги стран
- `erikwang2013/security-php` — инструменты обнаружения угроз безопасности
- `erikwang2013/poster-php` — случайная проверка чувствительных операций
- `erikwang2013/clickhouse-php` — подключение ClickHouse и вычисление вероятностей

## Структура проекта

```
game-platform-php/
├── admin/                     # Административная панель (webman v2, порт по умолчанию 8789, настраивается через APP_PORT)
│   ├── app/admin/v1/controller/  #   Контроллеры административной части
│   ├── app/middleware/        #   Промежуточное ПО (Cors/SecurityFilter/RateLimit/AdminAuth/AdminPermission/OperationLog)
│   ├── app/model/             #   Модели только для admin (8; остальные 52 общих модели — в packages/)
│   ├── app/service/           #   Сервисы только для admin (WalletService/WalletScope/RiskSandboxService)
│   ├── app/process/           #   Постоянные процессы (Http/Monitor/RiskIpCron)
│   ├── app/provider/          #   Слой игровых провайдеров (Self/ThirdParty/Factory)
│   ├── app/activity/          #   Движок активностей (ежедневный вход/приглашения/ежедневные задания)
│   ├── app/event/             #   Шина событий (EventBus Redis Pub/Sub)
│   ├── config/                #   Файлы конфигурации
│   └── apps/                  #   Фронтенды админки (4 варианта, обращаются к /admin/v1 → admin:8789)
│       ├── flutter/           #     Flutter Web PC административная панель
│       ├── react/             #     Консоль администрирования React 19 (Vite)
│       ├── angular/           #     Консоль администрирования Angular 21
│       └── harmonyos/         #     Консоль администрирования HarmonyOS ArkTS (.hap, минуя nginx)
│
├── service/                   # C-бизнес (webman v2, порт по умолчанию 8792, настраивается через APP_PORT)
│   ├── app/api/v1/controller/ #   API-контроллеры C-стороны
│   ├── app/middleware/        #   Промежуточное ПО (TraceId/Cors/SecurityFilter/RateLimit/LanguageMiddleware/UserAuth/ProviderAuth/SdkSessionAuth)
│   ├── app/model/             #   Модели только для service (10; остальные 52 общих модели — в packages/)
│   ├── app/service/           #   Сервисы только для service (кошелёк/риски/комплаенс/сверка/push/достижения/антифрод и т. д.)
│   ├── app/payment/           #   18 адаптеров платёжных шлюзов (Stripe/PayPal/Adyen/NowPayments/Skrill…) + GatewayFactory
│   ├── app/cdn/               #   CDN-адаптеры пяти провайдеров (Cloudflare/CloudFront/Alibaba/Tencent/Huawei) + CdnFactory
│   ├── app/process/           #   Постоянные процессы (Http/Monitor/LeaderboardWS:8790/ChatWS:8791/EventConsumer/EventSubscriber/AntiCheatWorker/GroupSweepWorker/Health)
│   ├── app/provider/          #   Слой игровых провайдеров
│   ├── app/activity/          #   Движок активностей
│   ├── app/event/             #   Шина событий (EventBus Redis Pub/Sub)
│   └── config/                #   Файлы конфигурации
│
├── packages/platform-common/  # Общий слой: admin и service подключают его через composer path-репозиторий, чтобы избежать двух копий
│   ├── src/model/             #   Общие Eloquent-модели (52, один источник для обеих сторон)
│   ├── src/service/           #   Общие сервисы (DepositLogService / VipService и др., 11 штук, включая вычисление вероятностей в ClickHouse)
│   ├── src/BcMath.php         #   Высокоточные вычисления сумм/курсов (обёртка над bcmath), округление, проценты
│   ├── src/EncryptionService.php  #   Шифрование/расшифровка AES и маскирование
│   ├── src/CircuitBreaker.php #   Предохранитель (плюс Retry.php для повторов)
│   ├── src/HashidsService.php #   Кодирование/декодирование ID на уровне API
│   └── src/SnowflakeService.php   #   Глобально уникальные BIGINT-идентификаторы
│
├── apps/                      # Фронтенды игроков C-части (4 варианта, обращаются к /api/v1 → service:8792)
│   ├── flutter/platform/      #   Flutter Web PC пользовательская платформа C-стороны
│   ├── react/                 #   React 19 (Vite) C-часть
│   ├── angular/               #   Angular 21 C-часть
│   └── harmonyos/             #   HarmonyOS ArkTS C-часть (.hap, минуя nginx)
│
├── game/xiaoxiaole/           # Встроенная мини-игра «Деревенский три-в-ряд»: TypeScript + Vite + Vitest, движок src/domain + дизайн из четырёх уровней + tests/, документы дизайна на 13 языках
│
├── install/                   # Мастер установки в один клик + SQL инициализации базы данных
│   ├── index.php              #   Точка входа установки
│   ├── Installer.php          #   Основная логика установки
│   ├── install.sql            #   Объединенный SQL установки (78 таблиц + стартовые данные)
│   ├── clickhouse.sql         #   DDL аналитической базы ClickHouse (отдельный движок, импортируется отдельно)
│   ├── test-data.sql          #   Демонстрационные/тестовые данные
│   ├── migrations/            #   Скрипты инкрементального обновления существующих баз (*.sql)
│   ├── lang/ + lang.php       #   Переводы интерфейса мастера установки (13 языков)
│   └── assets/                #   Статические ресурсы
│
├── docs/                      # Документация проекта (все тексты на 13 языках: .md — китайский исходник, рядом переводы .{lang}.md)
│   ├── ARCHITECTURE.md        #   Документ по архитектуре
│   ├── ARCHITECTURE-DESIGN.md #   Документ по проектированию архитектуры
│   ├── FEATURES.md            #   Документ по функциям
│   ├── FEATURE-DESIGN.md      #   Документ по проектированию функций
│   ├── API.md                 #   Документация по API
│   ├── DEPLOYMENT.md          #   Документ по развертыванию (Docker/вручную/настройка портов)
│   ├── PROVIDER-SDK.md        #   Руководство по подключению сторонних игр (алгоритм подписи + примеры на PHP/Go/Python)
│   ├── CLICKHOUSE_INSTALL.md  #   Установка/настройка/миграция/проверка ClickHouse
│   ├── CLICKHOUSE_USAGE.md    #   4 сервисных API ClickHouse и админ-панель
│   ├── translations/          #   Переводы этого README на 12 языков
│   ├── diagrams/              #   SVG по архитектуре/потокам/функциям/жизненному циклу/безопасности/расширению экосистемы (по 13 языков)
│   ├── test-reports/          #   Отчёты о тестах (php-unit / api / resilience / ui / SUMMARY)
│   └── superpowers/           #   Проектные спецификации и планы реализации этого репозитория (исторический архив)
│
├── scripts/                   # Эксплуатационные скрипты (проверка дрейфа моделей / миграция аннотаций apidoc / миграция семантики выплат exchange / проверка подписи)
├── tests/api/                 # Автотесты API (run_all.sh)
├── runtime/                   # Каталог времени выполнения webman (логи/pid, создаётся во время работы)
│
├── docker-compose.yml         # Оркестрация Docker Compose (порты по умолчанию из корневого .env)
├── nginx.conf.template        # Шаблон конфигурации Nginx (порты upstream рендерятся через envsubst)
├── .env.example               # Шаблон корневого .env (переменные портов Docker, скопируйте в .env для использования)
└── admin/docs/superpowers/    # Стандарты разработки и планы
    ├── specs/                 #   Спецификации дизайна
    └── plans/                 #   Планы реализации
```

## Быстрый старт

### Требования к окружению
- PHP 8.1+
- MySQL 8.0+
- Redis 6.0+
- Composer 2.x
- Flutter SDK 3.x (фронтенд, опционально)

### Способ 1: мастер установки в один клик (рекомендуется)

```bash
# 1. Запустите мастер установки
php -S 0.0.0.0:8888 -t install/

# 2. Откройте в браузере http://localhost:8888
#    Пройдите по шагам мастера: проверка окружения → настройка БД → создание аккаунта администратора → автоматическая установка

# 3. Установите зависимости
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 4. Запустите сервисы (порты по умолчанию admin 8789 / service 8792, меняются в APP_PORT соответствующего .env)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 5. Откройте админ-панель: http://localhost:8789 (порт по умолчанию)
#    Войдите с логином и паролем администратора, заданными при установке

# 6. После установки удалите каталог установки (безопасность)
rm -rf install/
```

Мастер установки автоматически выполнит:
- Проверку окружения (версия PHP, расширения, права на каталоги)
- Создание базы данных и таблиц (объединенный SQL, 78 таблиц + стартовые данные)
- Создание аккаунта супер-администратора (шифрование bcrypt)
- Автоматическую генерацию JWT/ключей шифрования и запись в файл .env
- Создание install.lock для предотвращения повторной установки

### Способ 2: ручная установка

<details>
<summary>Развернуть шаги ручной установки</summary>

#### 1. Инициализация базы данных

```bash
# Импорт объединенного SQL одним махом
mysql -u root -e "CREATE DATABASE IF NOT EXISTS game-platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root game-platform < install/install.sql
```

#### 2. Настройка переменных окружения

```bash
# Административная панель
cd admin
cp .env.example .env
# Отредактируйте в .env параметры подключения к БД и ключи

# C-бизнес
cd ../service
cp .env.example .env
# Отредактируйте в .env параметры подключения к БД и ключи
```

#### 3. Запуск бэкенда

```bash
cd admin && composer install && php start.php start -d
cd ../service && composer install && php start.php start -d
```

#### 4. Создание администратора

Необходимо вручную вставить аккаунт администратора в базу данных (пароль шифруется с помощью bcrypt).

</details>

### Запуск фронтенда (опционально)

В режиме разработки каждая сторона запускает свой dev-сервер; он проксирует запросы к соответствующему бэкенду (см. `proxy.conf.json` / `vite.config.ts` в каждом каталоге):

```bash
# --- Платформа игрока C-части (/api/v1 → service:8792) ---
cd apps/react            && npm install && npm run dev      # http://localhost:5173
cd apps/angular          && npm install && npm start        # http://localhost:4200
cd apps/flutter/platform && flutter pub get && flutter run -d chrome

# --- Консоль администрирования (/admin/v1 → admin:8789) ---
cd admin/apps/react      && npm install && npm run dev      # http://localhost:5273
cd admin/apps/angular    && npm install && npm start        # http://localhost:4300
cd admin/apps/flutter    && flutter pub get && flutter run -d chrome
```

> Порты dev-серверов Angular: консоль администрирования явно задаёт 4300 в `angular.json`, а C-часть использует стандартный для Angular 4200; чтобы запустить оба сразу, добавьте `--port` одному из них.
> Цели HarmonyOS (`apps/harmonyos`, `admin/apps/harmonyos`) открываются и собираются в DevEco Studio;
> эмулятор обращается к бэкенду на хост-машине по `http://10.0.2.2:<port>` (см. константу в начале соответствующего `ApiService.ets`).

### Развёртывание фронтенда (Docker/Nginx)

Сервис nginx из `docker-compose.yml` монтирует артефакты сборки каждого фронтенда в контейнер только для чтения, а `nginx.conf.template` отдаёт их по путям ниже.
Если артефакт не собран, каталог пуст: запросы к пути возвращают 404, запросы к «голому» каталогу (например, `/app-react/`) — 403.

| URL | Точка монтирования артефакта | Команда сборки |
|-----|-----------|---------|
| `/` | `apps/flutter/platform/build/web` | `flutter build web` |
| `/app-react/` | `apps/react/dist` | `npm run build` (скрипт содержит `--base=/app-react/`) |
| `/app-angular/` | `apps/angular/dist/game-client-angular/browser` | `npm run build` (скрипт содержит `--base-href=/app-angular/`) |
| `/admin-panel/` | `admin/public` | Универсальное место размещения: скопируйте артефакт любой консоли в `admin/public`; если его нет, ответ тоже 404 («голый» каталог — 403). Артефакт должен быть собран с `--base=/admin-panel/` (для Flutter — `--base-href=/admin-panel/`), иначе его ресурсы по-прежнему указывают на исходный префикс и дают 404. Форма без слэша перенаправляется 301 на этот адрес; в `nginx.conf.template` задан `absolute_redirect off`, поэтому перенаправление — относительный Location, и развёртывания на портах, отличных от 80, больше не теряют порт |
| `/admin-react/` | `admin/apps/react/dist` | `npm run build` (скрипт содержит `--base=/admin-react/`) |
| `/admin-angular/` | `admin/apps/angular/dist/game-admin-angular/browser` | `npm run build` (скрипт содержит `--base-href=/admin-angular/`) |
| `/admin-flutter/` | `admin/apps/flutter/build/web` | `flutter build web --base-href=/admin-flutter/` |

`/admin/` (API) → контейнер admin, `/api/` (API) → контейнер service; клиент HarmonyOS распространяется пакетом `.hap` и не проходит через nginx.

### Проверка

```bash
# Проверка админ-панели (порт по умолчанию 8789)
curl http://localhost:8789/health

# Проверка C-бизнеса (порт по умолчанию 8792)
curl http://localhost:8792/health

# Проверка регистрации пользователя
curl -X POST http://localhost:8792/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"Abcdef12"}'
```

## Функции безопасности

- **18 уровней эшелонированной обороны**: обнаружение и блокировка XSS/SQL-инъекций/CSRF/обхода пути/инъекций команд
- **Белый список HTTP-методов**: разрешены только GET/POST/PUT/DELETE/OPTIONS/HEAD
- **JWT-аутентификация**: access_token 2 ч + refresh_token 14 дней, ограничение параллельных сессий
- **Проверка JWT-ключей при запуске**: у admin — `ADMIN_JWT_SECRET_KEY`, у service — `SERVICE_JWT_SECRET_KEY`, отдельные ключи; при отсутствии или оставшемся значении по умолчанию запуск отклоняется
- **Платежные callback'и fail-closed**: белый список провайдеров (только stripe/paypal) + отклонение при отсутствии ключа/неудачной проверке подписи/выходе временной метки за пределы + сверка сумм через bccomp + транзакционное зачисление по callback'у
- **RBAC-права**: контроль доступа с гранулярностью method.path, кэш в Redis 60 с
- **Клик-капча**: обязательная человеко-машинная проверка при входе/регистрации
- **Второе подтверждение пароля**: чувствительные операции требуют ввода пароля
- **Шифрование данных**: AES-256-CBC на транспортном уровне + AES-128-ECB на уровне хранения
- **Шифрование ID**: генерация Snowflake + кодирование Hashids, необратимо для внешнего мира
- **Оптимистичные блокировки кошелька**: защита от параллельного списания/повторного зачисления
- **Аудит операций**: полный журнал операций, автоматическое определение 8 платформ-источников
- **Ограничение частоты**: Redis скользящее окно, атомарность через Lua
- **CSP-заголовок**: Content-Security-Policy против XSS
- **Безопасность аккаунта**: блокировка на 15 минут после 5 неудачных входов подряд

## Тестирование

Отчёты о тестах (хранятся локально): [docs/test-reports/](../test-reports/)

| Тип теста | Кейсы/покрытие | Результат |
|---------|----------|------|
| Модульные тесты PHP | текущий замер `phpunit --list-tests`: admin 200 + service 273 кейса (в отчёте `docs/test-reports/php-unit.md` зафиксированы повторный прогон 09-22 admin 190 + service 273 и снимок 08-27 admin 153 + service 45; сторона admin ещё дополняется) | service всё проходит (701 утверждение, 3 skipped, 2 warnings + 35 deprecations); admin 437 утверждений, 3 skipped, 1 сбой (`EnvConfigTest` проверяет реальный `admin/.env` и не находит `REDIS_CLUSTER_NODES`; с ним тест станет зелёным) |
| Тесты механизмов стабильности | предохранитель/повторы/переключатель деградации, 15 кейсов (CircuitBreakerTest/RetryTest/ResilienceMockTest) | всё проходит |
| Автотесты API | 187 эндпоинтов (источник: `docs/test-reports/api.md`, 2026-08-27); сейчас route.php регистрирует 261 эндпоинт | 171 прошёл / 50 сбоев / 4 пропущено (все сбои — детерминированные дефекты, см. отчёт) |
| UI-тесты Flutter | 12 кейсов (вход/дашборд/навигация/смена языка) | всё проходит |
| Go/Rust | кода на Go/Rust в репозитории нет | пропущено, зафиксировано |

```bash
# Модульные тесты PHP (сначала экспортируйте переменные окружения с секретом JWT)
cd admin && ADMIN_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
# Автотесты API (сервисы должны быть запущены, см. tests/api/run_all.sh)
bash tests/api/run_all.sh
# UI-тесты Flutter
cd admin/apps/flutter && flutter test --timeout 300s
```

Подробные отчёты:
- [Отчёт о модульных тестах PHP](../test-reports/php-unit.md)
- [Отчёт о тестах механизмов стабильности (предохранитель/повторы/деградация)](../test-reports/resilience.md)
- [Отчёт об автотестах API](../test-reports/api.md)
- [Отчёт о UI-тестах Flutter](../test-reports/ui.md)

## Обзор возможностей платформы

| Возможность | Описание |
|------|------|
| Аутентификация пользователей | Имя/пароль + OAuth 7 платформ (Google/Facebook/Apple/X(Twitter)/Microsoft/LinkedIn/GitHub) + 2FA TOTP |
| Кошелек | Кошелек платформенных монет (оптимистичная блокировка) + кошелек игровой валюты + журнал операций |
| Пополнение | Создание заказа + проверка подписи callback'ов Stripe/PayPal + автоматическое зачисление |
| Обмен | Платформенные монеты ⇄ игровая валюта, котировки в реальном времени, доход со спреда |
| Вывод | Заявка→проверка→выплата, глобальный переключатель, KYC-ступенчатые лимиты + комиссии |
| KYC | Подача и проверка верификации личности, повышает лимит вывода после одобрения |
| Игры | CRUD + категории (10 категорий) + серверы + отслеживание игровых записей |
| Поиск | Полнотекстовый поиск Elasticsearch (с откатом на LIKE) |
| Рейтинги | Дневной/недельный/месячный/общий, кэш Redis, push в реальном времени по WebSocket (порт по умолчанию 8790, настраивается через LEADERBOARD_WS_PORT) |
| CDN | Интеграция пяти провайдеров (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS загрузка + очистка кэша + прогрев) + настройка/включение/проверка соединения в админ-панели |
| Купоны | Фиксированная сумма + процентная скидка, ограничение по времени и количеству, отслеживание выдачи и использования |
| Уведомления | Внутренние сообщения + email, автоуведомления о пополнении/выводе/KYC/купонах |
| Реферальная система | Реферальный код, бонус за регистрацию, комиссия с пополнений |
| Риск-контроль | Черный список IP/предупреждения о крупных суммах/проверка частоты/скорости |
| Углублённый риск-контроль | Отпечаток устройства / репутация IP / граф связей аккаунтов + движок правил + дашборд рисков + AML/KYC/доверие |
| Античит | Сбор событий античита + дневная статистика + ручная проверка |
| Сверка/расчёты | Ежедневные сверки + детали расхождений + сверка выписок |
| Единый кошелёк | WalletScope единый кошелёк |
| Движок акций | Создание/участие/награды акций + чек-ин |
| Социальный рост | Группы + отслеживание реферальных ссылок |
| Платёжные шлюзы | Новые шлюзы Adyen / GrabPay (L1) |
| Интернационализация | 4 языка (en-US/zh-CN/ja-JP/ko-KR), таблицы переводов + кэш |
| Настройка стран | 18 стран с разными способами оплаты/вывода, минимальными суммами пополнения |
| Статистика | Ежедневные снимки (5 типов метрик) + отслеживание дохода платформы |
| Капча | Клик-капча человеко-машинной проверки (poster-php) |
| Подключение игр | Provider SDK (Self+ThirdParty) + подпись HMAC-SHA256 + шлюз callback'ов |
| Тикеты | Создание/ответ с C-стороны + обработка/назначение/закрытие в админ-панели |
| VIP | 5 уровней лояльности, накопление опыта, скидки на обмен/снижение комиссий за вывод/бонус к курсу |
| Достижения | 12 встроенных достижений, обнаружение на основе событий, отслеживание прогресса |
| Соцсеть | Система друзей + личные сообщения в реальном времени по WebSocket (порт по умолчанию 8791, настраивается через CHAT_WS_PORT), отправка только друзьям |
| Турниры | Система турниров (переключатель FeatureFlag) + рейтинг + лимит участников |
| Реферальные выплаты | Двухуровневое распределение прибыли (настраиваемые ставки комиссии) |
| Купоны | Ограничения по условиям (min_deposit/first_user/game_id) |
| События | Шина событий Redis Pub/Sub + доставка Webhook-подписок (7 типов событий) |
| Деплой | Оркестрация Docker Compose из 7 сервисов (порты настраиваются в корневом .env) + обратный прокси Nginx |
| Клиенты | Админка 4 варианта (Flutter/React/Angular/HarmonyOS) + C-часть 4 варианта (Flutter/React/Angular/HarmonyOS) |

## Бизнес-модель

```
Фиат (USD/CNY/EUR...)
  │  Пополнение (Stripe/PayPal/Alipay/WeChat Pay)
  ▼
Платформенные монеты (единые, точность decimal(18,4))
  │  Обмен (включая курс + комиссию платформы со спреда)
  ▼
Игровая валюта (у каждой игры своя, свой курс)
  │  Заработок/трата в играх
  ▼
Платформенные монеты ← обмен обратно → Вывод (проверка/автоматически)
```

## Мультивалютные расчеты

Платформа использует трехуровневую валютную изоляцию «фиат → платформенные монеты → игровая валюта»: поддерживается пополнение в нескольких фиатных валютах (USD/CNY/EUR/JPY/KRW/GBP/BRL/INR), у каждой игры своя расчетная валюта; все денежные расчеты выполняются с высокой точностью через bcmath, исключая ошибки с плавающей точкой.

### Трехуровневая валютная модель

| Уровень | Валюта | Описание |
|------|------|------|
| Фиатный уровень | USD / CNY / EUR / JPY / KRW / GBP / BRL / INR | Реальная платежная валюта пополнений/выводов пользователя, обрабатывается Stripe / PayPal |
| Уровень платформенных монет | Платформенные монеты (единые на всю платформу) | Единая внутренняя расчетная валюта (decimal(18,4)), оптимистичные блокировки кошелька против параллельного списания/повторного зачисления |
| Уровень игровой валюты | У каждой игры своя валюта | У каждой игры свой курс `exchange_rate` и спред `spread_pct`, отдельный кошелек игровой валюты |

### Пути расчетов

- **Пополнение**: пользователь платит в фиате (проверка подписи callback'ов Stripe / PayPal, идемпотентность) → конвертация в платформенные монеты по `default_exchange_rate`, в заказе пополнения фиксируются `amount + currency + platform_amount`
- **Расчет обмена**: платформенные монеты ⇄ игровая валюта по курсу игровой валюты в реальном времени (quote), вычитается спред `spread_pct` как доход платформы со спреда, VIP получает скидки на обмен и бонус к курсу
- **Игровые расчеты**: игровой провайдер через callback `/api/provider/settle` начисляет/списывает игровую валюту (подпись HMAC-SHA256), сессия игры при таймауте рассчитывается автоматически
- **Расчет вывода**: списание платформенных монет → создание заказа на вывод (фиксируются `platform_amount / fiat_amount / currency`) → одобрение в админ-панели → выплата PayPal Payout → синхронизация статуса партии до завершения

### Схема расчетов

```mermaid
flowchart LR
    subgraph FIAT["法币层 Fiat"]
        A["用户充值<br/>USD / CNY / EUR / JPY / KRW / GBP / BRL / INR<br/>Stripe / PayPal"]
        H["提现到账<br/>PayPal Payout"]
    end

    subgraph PLAT["平台币层 Platform Token"]
        B["平台币钱包<br/>decimal(18,4) 乐观锁"]
        E["提现订单<br/>platform_amount<br/>fiat_amount / currency"]
    end

    subgraph GAME["游戏币层 Game Currency"]
        D["游戏币种<br/>exchange_rate<br/>spread_pct"]
        C["游戏币钱包<br/>UserGameWallet"]
        G["游戏 Provider<br/>settle 结算回调"]
    end

    A -->|"充值回调验签<br/>平台币 = 法币 × default_exchange_rate"| B
    B -->|"兑换买入 in<br/>扣除点差"| C
    C -->|"兑换卖出 out<br/>按汇率折算"| B
    D -.->|"独立汇率 + VIP 加成"| C
    G <-->|"玩游戏赚/花"| C
    B -->|"提现申请（扣款）"| E
    E -->|"管理端审批<br/>PayPal Payout 打款"| H
```

## Архитектура

![Схема системной архитектуры](../diagrams/architecture-ru.svg)

## Ключевые бизнес-процессы

![Схема бизнес-процессов](../diagrams/flow-ru.svg)

## Обзор функций

![Схема функционала](../diagrams/features-ru.svg)

## Жизненный цикл

![Схема жизненного цикла](../diagrams/lifecycle-ru.svg)

## Архитектура безопасности

![Схема архитектуры безопасности](../diagrams/security-ru.svg)

## Экосистемное расширение (v2.0)

![Схема архитектуры экосистемного расширения](../diagrams/ecosystem-expansion-ru.svg)

## Индекс документации

| Документ | Описание |
|------|------|
| [Сравнение версий](../VERSIONS.ru.md) | Сравнение функций базовой/стандартной/полной версий |
| [Проектирование архитектуры](../ARCHITECTURE-DESIGN.ru.md) | Обоснование выбора архитектуры и проектные решения |
| [Архитектура](../ARCHITECTURE.ru.md) | Топология системы, модульная архитектура, потоки данных |
| [Проектирование функций](../FEATURE-DESIGN.ru.md) | Бизнес-модели, функциональные спецификации, проектирование процессов |
| [Функции](../FEATURES.ru.md) | Перечень функций, описание модулей, пользовательские сценарии |
| [API](../API.ru.md) | Полный справочник API (146 интерфейса) |
| [Онлайн-документация](http://localhost:8792/apidoc/) | Интерактивная документация erikwang2013/apidoc-php (C-сторона) |
| [Онлайн-документация](http://localhost:8789/apidoc/) | Интерактивная документация erikwang2013/apidoc-php (админ-панель) |
| [Установка ClickHouse](../CLICKHOUSE_INSTALL.ru.md) | Установка/настройка/миграция/проверка ClickHouse |
| [Документация Provider SDK](../PROVIDER-SDK.ru.md) | Руководство по подключению сторонних игр (алгоритм подписи + примеры PHP/Go/Python) |
| [Использование ClickHouse](../CLICKHOUSE_USAGE.ru.md) | 4 сервисных API ClickHouse и панель в админке |
| [Деплой](../DEPLOYMENT.ru.md) | Руководство по развертыванию (Docker + вручную + Nginx + мониторинг) |
| [Спецификация дизайна](../../admin/docs/superpowers/specs/2026-05-22-game-platform-design.ru.md) | Полная спецификация дизайна |
| [План реализации](../../admin/docs/superpowers/plans/2026-05-22-game-platform-plan.ru.md) | Подробный план реализации |

---

## Поддержка проекта

Если этот проект вам помог, угостите автора чашечкой кофе ☕

<p align="center">
  <table align="center" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center" width="200">
        <img src="../weixinpay-130.png" width="130" height="130" alt="微信支付"><br>
        <b>WeChat Pay</b>
      </td>
      <td align="center" width="200">
        <img src="../alipay-130.png" width="130" height="130" alt="支付宝"><br>
        <b>Alipay</b>
      </td>
    </tr>
  </table>
</p>

### Глобальный банковский перевод (Global Bank Transfer)

**Получатель (Recipient)**

| Поле | Содержание |
|----|------|
| Имя получателя (Beneficiary Name) | WANG KEXUN |
| Номер счета (Account Number) | 881015918251 |

**Банк получателя (Beneficiary Bank)**

| Поле | Содержание |
|----|------|
| SWIFT Code | AABLHKHHXXX |
| Название банка (Bank Name) | ZA Bank Limited |
| Код банка (Bank Code) | 387 |
| Адрес банка (Bank Address) | Core F, Cyberport 3, 100 Cyberport Road, Hong Kong |

**Банк-корреспондент для трансграничных переводов (Correspondent Bank, при необходимости)**

> Обратите внимание: это информация о банке-корреспонденте (банке-посреднике) для трансграничных переводов, а не о банке получателя. Уточните в банке отправителя, требуется ли предоставить информацию о банке-корреспонденте.

- **Для переводов в гонконгских долларах, юанях и долларах США банк-корреспондент — Citibank:**
  - Название банка: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - Код банка: 006
  - Название отделения: Hong Kong Branch
  - Код отделения: 391
  - Адрес банка: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **Для переводов в других валютах банк-корреспондент — BNY Mellon:**
  - Название банка: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - Адрес банка: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Пожертвование в криптовалюте (Crypto Donation)

Если этот проект помог вам, отсканируйте QR-код, чтобы сделать пожертвование, спасибо!

| Сеть (Network) | QR-код (QR Code) | Адрес кошелька (Wallet Address) |
|---|---|---|
| BNB Smart Chain (BEP20) | [<img src="../coin/1.jpg" width="150" alt="BNB Smart Chain (BEP20)">](../coin/1.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Tron (TRC20) | [<img src="../coin/2.jpg" width="150" alt="Tron (TRC20)">](../coin/2.jpg) | `TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| Ethereum (ERC20) | [<img src="../coin/3.jpg" width="150" alt="Ethereum (ERC20)">](../coin/3.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Aptos | [<img src="../coin/4.jpg" width="150" alt="Aptos">](../coin/4.jpg) | `0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| Plasma | [<img src="../coin/5.jpg" width="150" alt="Plasma">](../coin/5.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Polygon POS | [<img src="../coin/6.jpg" width="150" alt="Polygon POS">](../coin/6.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Solana | [<img src="../coin/7.jpg" width="150" alt="Solana">](../coin/7.jpg) | `2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` |
| The Open Network (TON) | [<img src="../coin/8.jpg" width="150" alt="The Open Network (TON)">](../coin/8.jpg) | `UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| Arbitrum One | [<img src="../coin/9.jpg" width="150" alt="Arbitrum One">](../coin/9.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| AVAX C-Chain | [<img src="../coin/10.jpg" width="150" alt="AVAX C-Chain">](../coin/10.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |

