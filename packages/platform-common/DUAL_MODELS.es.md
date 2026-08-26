# Inventario de modelos restantes con doble copia
<!-- lang-nav -->

Languages: [中文](DUAL_MODELS.md) · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · **Español** · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` solo extrae **common/service**. Los modelos Eloquent y los wrappers de `app/common` ligados al host siguen duplicados entre **admin** y **service**.

## Conteos

| Lado | Número de modelos |
|------|-------------|
| `app/model` de admin | 46 |
| `app/model` de service | 44 |
| Nombres compartidos (siguen duplicados) | 39 |

Los modelos con nombre compartido siguen existiendo como **dos copias**. Cambiar un lado obliga a sincronizar el otro hasta que se entregue un paquete de modelos.

## Modelos con nombre compartido (duplicados)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## Solo en admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## Solo en service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## Wrappers de app/common (siguen duplicados, ligados al host)

Estos permanecen en cada aplicación host (el cableado de plugin/config es específico del host):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Regla

**Cambiar un lado obliga a sincronizar el otro** hasta que se entregue un paquete de modelos dedicado. `common\service\*` es la copia única en `packages/platform-common`.
