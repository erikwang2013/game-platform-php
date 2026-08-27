# جرد النماذج المزدوجة المتبقية
<!-- lang-nav -->

Languages: **中文** · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


استخرج `erik/platform-common` **common/service** فقط. تبقى نماذج Eloquent وغلافات `app/common` المرتبطة بالمضيف منسوخة بين **admin** و**service**.

## الأعداد

| الجانب | عدد النماذج |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| الأسماء المشتركة (ما زالت مزدوجة) | 39 |

نماذج الأسماء المشتركة ما زالت موجودة **كنسختين**. تغيير أحد الجانبين يتطلب مزامنة الآخر حتى تصدر حزمة نماذج.

## النماذج ذات الأسماء المشتركة (مزدوجة)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## خاص بـ admin فقط

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## خاص بـ service فقط

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## غلافات app/common (تبقى مزدوجة، مرتبطة بالمضيف)

تبقى هذه في كل تطبيق مضيف (ربط الإضافات/الإعدادات خاص بالمضيف):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## القاعدة

**تغيير أحد الجانبين يتطلب مزامنة الآخر** حتى تصدر حزمة نماذج مخصصة. `common\service\*` هي النسخة المفردة في `packages/platform-common`.
