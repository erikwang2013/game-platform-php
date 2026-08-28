# Changelog
<!-- lang-nav -->

Languages: **中文** · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Человекочитаемый журнал изменений. PHP не импортирует этот файл. Соответствует PROJECT-PLAN P2-21.

## [1.1] — 2026-08-07

- Подключение Redis-плагина, аналитические сервисы, деградация Redis, исправление тестов.

## [1.1] security / ops — 2026-08-18

### Безопасность

- Платёжные колбэки: белый список провайдеров (stripe/paypal), fail-closed проверка подписи, сверка суммы, транзакционное зачисление, анти-реплей по таймстампу Stripe ±300s.
- JWT: при отсутствии `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` или при значении по умолчанию — отказ запуска.
- Apple id_token: проверка подписи JWKS (RS256) + aud/iss/exp.
- Webhook: только публичные https-URL, отказ для внутренних/зарезервированных адресов (SSRF).
- 2FA: TOTP HMAC использует ключ после декодирования RFC 4648 Base32; `/api/2fa/verify` — блокировка по пользователю после неудач (5 раз / 15 минут, при сбое Redis — fail-closed).
- Вывод средств: атомарное переключение статуса при проверке/выплате; опциональная двойная проверка (`withdraw.require_dual_review`); на стороне заявки Redis-блокировка пользователя против прорыва лимитов конкурентными запросами.
- Ограничение частоты: при сбое Redis — fail-closed.

### Доступность

- Подключены 12 маршрутов `/admin/analytics/*` аналитических сервисов admin.
- Модели без жёстко зашитого префикса `game_`; аудит DepositLog пишется в БД; тестовая модель Test удалена.

### Наблюдаемость

- `GET /metrics` дополнен: ожидающие проверки выводы, подтверждённые пополнения за сегодня (COUNT-запрос, кэш Redis 30s), счётчики emit/consume событий, `memory_usage`, `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` по crc32-бакетам читает `feature.{name}_percent`.
- EventBus `emit` / `consume` делают INCR по `metrics:event_emit_total` / `metrics:event_consume_total` в Redis.

### Клиенты / общий слой (дополнено тем же днём)

- Flutter Platform: таблица маршрутов `app_pages.dart`; добавлены страницы настройки/проверки 2FA, купонов, рейтингов, уведомлений, OAuth-колбэка; точка входа лобби подключена к навигации.
- HarmonyOS C-сторона: `apps/harmonyos/` — пять страниц (вход/лобби/детали/кошелёк/личный кабинет), `BASE_URL` по умолчанию указывает на service `8788`.
- Общий слой: `packages/platform-common` (path-репозиторий `erik/platform-common`) — вынесены DepositLog / GameDashboard / Probability / GamePlayLog; модели по-прежнему в двух копиях.
- ClickHouse: composer-зависимость снята; аналитика по-прежнему идёт через реальную агрегацию MySQL.
- CI: admin / service в отдельных job'ах гоняют phpunit, при провале — блокировка.

### Оставшиеся пробелы

- **Модели** admin/service по-прежнему в двух копиях (лишь часть `common/service` вошла в path-пакет).
- `webman/queue` не подключён; вероятность/удержание не перенесены на OLAP.
- PROJECT-PLAN / VERSIONS / части отчётов аудита могут отставать от этого CHANGELOG; источник истины — этот файл и диск.

## [1.1] resilience — 2026-08-27

### Стабильность

- В общий слой добавлены `CircuitBreaker` (состояние в Redis, порог 5 / окно 30 с, fail-open при недоступности Redis) и `Retry` (экспоненциальная задержка, только сетевые исключения, максимум 5 попыток), в `packages/platform-common/src/`.
- Переключатель деградации `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider при `on` пропускают реальные сетевые вызовы.
- Исправлено 11 дефектов типа `getenv($name, '')` (TypeError при strict_types); проверка mock в PushService перенесена в try/catch.
- Новые тесты: CircuitBreakerTest / RetryTest / ResilienceMockTest; набор service 45 → 60 кейсов, все зелёные (отчёт: [test-reports/resilience.md](test-reports/resilience.md)).

## [1.1] payments — 2026-08-29

- Мульти-платёжные шлюзы: Stripe Checkout / NOWPayments (USDT TRC20+ERC20) / Coinbase Commerce (USDC).
- CRUD платёжных методов в админке + видимость по странам + диапазоны сумм; при создании заказа пополнения сразу заполняются checkout_url / expires_at.
- Новая миграция install/migrations/2026_08_29_multi_payment.sql (необходимо выполнить).
