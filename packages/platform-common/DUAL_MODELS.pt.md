# Inventário dos modelos remanescentes em cópia dupla
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` extraiu apenas **common/service**. Os modelos Eloquent e os wrappers `app/common` ligados ao host permanecem duplicados entre **admin** e **service**.

## Contagens

| Lado | Nº de modelos |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Nomes compartilhados (ainda em duplicidade) | 39 |

Os modelos de nome compartilhado ainda existem como **duas cópias**. Alterar um lado exige sincronizar o outro até que um pacote de modelos seja criado.

## Modelos de nome compartilhado (duplicados)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## Somente admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## Somente service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## Wrappers de app/common (permanecem duplicados, ligados ao host)

Estes permanecem em cada aplicativo host (a fiação de plugin/config é específica do host):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Regra

**Alterar um lado exige sincronizar o outro** até que um pacote de modelos dedicado seja criado. `common\service\*` é a cópia única em `packages/platform-common`.
