# 남아 있는 이중 복사 모델 인벤토리
<!-- lang-nav -->

Languages: [中文](DUAL_MODELS.md) · [English](DUAL_MODELS.en.md) · **한국어** · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common`은 **common/service만** 추출했습니다. Eloquent 모델과 호스트 종속 `app/common` 래퍼는 여전히 **admin**과 **service** 양쪽에 중복되어 있습니다.

## 수량

| 측 | 모델 수 |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| 이름 공유（여전히 이중） | 39 |

이름을 공유하는 모델은 여전히 **두 벌**로 존재합니다. 한쪽을 바꾸면 모델 패키지가 나오기 전까지 다른 쪽도 동기화해야 합니다.

## 이름 공유 모델（이중）

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## admin 전용

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## service 전용

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common 래퍼（여전히 이중, 호스트 종속）

이들은 각 호스트 앱에 남습니다（플러그인/config 배선이 호스트별이므로）:

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## 규칙

**한쪽을 바꾸면 다른 쪽도 동기화해야 합니다**, 전용 모델 패키지가 나올 때까지. `common\service\*`는 `packages/platform-common`의 단일 복사본입니다.
