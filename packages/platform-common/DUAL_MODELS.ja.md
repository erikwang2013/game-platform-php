# 残存する二重コピーのモデル一覧
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` は **common/service** のみを抽出しました。Eloquent モデルとホスト依存の `app/common` ラッパーは、**admin** と **service** の間で二重コピーのまま残っています。

## 件数

| 側 | モデル数 |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| 同名共有（依然二重） | 39 |

同名モデルは今も**2コピー**存在します。モデルパッケージが登場するまで、一方を変更したらもう一方も同期する必要があります。

## 同名モデル（二重）

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## admin のみ

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## service のみ

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common ラッパー（二重のまま、ホスト依存）

これらは各ホストアプリに残ります（プラグイン/config の配線はホスト固有のため）：

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## ルール

専用のモデルパッケージが登場するまで、**一方を変更したらもう一方も同期**すること。`common\service\*` は `packages/platform-common` 内の単一コピーです。
