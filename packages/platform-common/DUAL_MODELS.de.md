# Inventar der verbleibenden Doppelkopie-Modelle
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` extrahiert nur **common/service**. Eloquent-Modelle und hostgebundene `app/common`-Wrapper bleiben zwischen **admin** und **service** dupliziert.

## Anzahlen

| Seite | Modellanzahl |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| Gemeinsamer Name (weiterhin doppelt) | 39 |

Modelle mit gemeinsamem Namen existieren weiterhin als **zwei Kopien**. Eine Änderung auf einer Seite muss bis zur Einführung eines Modell-Pakets auf der anderen Seite synchronisiert werden.

## Modelle mit gemeinsamem Namen (doppelt)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## nur admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## nur service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common-Wrapper (bleiben doppelt, hostgebunden)

Diese verbleiben in der jeweiligen Host-App (Plugin-/Konfig-Verdrahtung ist hostspezifisch):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## Regel

**Eine Änderung auf einer Seite muss die andere synchronisieren**, bis ein dediziertes Modell-Paket eingeführt wird. `common\service\*` ist die einzige Kopie in `packages/platform-common`.
