# Inventaris model salinan ganda yang tersisa
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` hanya mengekstrak **common/service**. Model Eloquent dan pembungkus `app/common` yang terikat host tetap disalin ganda antara **admin** dan **service**.

## Jumlah

| Sisi | Jumlah model |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Nama berbagi (masih ganda) | 39 |

Model bernama-berbagi masih ada sebagai **dua salinan**. Mengubah satu sisi harus menyinkronkan sisi lain sampai paket model jadi.

## Model bernama-berbagi (ganda)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## Hanya admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## Hanya service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## Pembungkus app/common (tetap ganda, terikat host)

Ini tetap di masing-masing aplikasi host (pengaitan plugin/config spesifik host):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Aturan

**Mengubah satu sisi harus menyinkronkan sisi lain** sampai paket model khusus jadi. `common\service\*` adalah salinan tunggal di `packages/platform-common`.
