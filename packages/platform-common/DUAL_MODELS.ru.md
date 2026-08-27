# Перечень моделей, оставшихся в двух копиях
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


Пакет `erik/platform-common` вынесен только для **common/service**. Eloquent-модели и привязанные к хосту обёртки `app/common` остаются продублированными между **admin** и **service**.

## Количество

| Сторона | Число моделей |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Общих имён (по-прежнему в двух копиях) | 39 |

Модели с общими именами по-прежнему существуют в **двух копиях**. Изменение одной стороны требует синхронизации другой, пока не появится пакет моделей.

## Модели с общими именами (в двух копиях)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## Только в admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## Только в service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## Обёртки app/common (остаются в двух копиях, привязаны к хосту)

Они остаются в каждом приложении-хосте (подключение плагинов/конфигурации специфично для хоста):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Правило

**Изменение одной стороны требует синхронизации другой**, пока не появится отдельный пакет моделей. `common\service\*` — единственная копия в `packages/platform-common`.
