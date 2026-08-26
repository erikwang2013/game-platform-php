# Remaining dual-copy model inventory
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` extracted **common/service** only. Eloquent models and host-bound `app/common` wrappers remain duplicated between **admin** and **service**.

## Counts

| Side | Model count |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Shared name (still dual) | 39 |

Shared-name models still exist as **two copies**. Changing one side must sync the other until a model package lands.

## Shared-name models (dual)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## admin-only

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## service-only

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common wrappers (remain dual, host-bound)

These stay in each host app (plugin/config wiring is host-specific):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Rule

**Change one side must sync the other** until a dedicated model package lands. `common\service\*` is the single copy in `packages/platform-common`.
