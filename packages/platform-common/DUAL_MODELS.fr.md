# Inventaire du modèle en double copie restant
<!-- lang-nav -->

Languages: [中文](DUAL_MODELS.md) · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · **Français** · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` n'a extrait que **common/service**. Les modèles Eloquent et les wrappers `app/common` liés à l'hôte restent dupliqués entre **admin** et **service**.

## Effectifs

| Côté | Nombre de modèles |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Nom partagé (toujours en double) | 39 |

Les modèles à nom partagé existent toujours en **deux copies**. Toute modification d'un côté doit être synchronisée avec l'autre jusqu'à la mise en place d'un package de modèles.

## Modèles à nom partagé (en double)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## Réservés à admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## Réservés à service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## Wrappers app/common (restent en double, liés à l'hôte)

Ils restent dans chaque application hôte (le câblage plugin/config est spécifique à l'hôte) :

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Règle

**Toute modification d'un côté doit être synchronisée avec l'autre** jusqu'à la mise en place d'un package de modèles dédié. `common\service\*` est l'unique copie dans `packages/platform-common`.
