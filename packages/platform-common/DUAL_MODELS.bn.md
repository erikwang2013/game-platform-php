# অবশিষ্ট ডুয়াল-কপি মডেল ইনভেন্টরি
<!-- lang-nav -->

Languages: [中文](DUAL_MODELS.md) · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · [हिन्दी](DUAL_MODELS.hi.md) · [العربية](DUAL_MODELS.ar.md) · **বাংলা** · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` শুধুমাত্র **common/service** নিষ্কাশন করেছে। Eloquent মডেল এবং হোস্ট-নির্ভর `app/common` র্যাপার **admin** ও **service**-এর মধ্যে ডুপ্লিকেট অবস্থায় রয়ে গেছে।

## সংখ্যা

| পাশ | মডেল সংখ্যা |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| শেয়ার্ড নাম (এখনও ডুয়াল) | 39 |

শেয়ার্ড-নামের মডেল এখনও **দুটি কপি** হিসেবে বিদ্যমান। এক পাশ পরিবর্তন করলে অন্যটিও সিঙ্ক করতে হবে, যতক্ষণ না একটি মডেল প্যাকেজ আসে।

## শেয়ার্ড-নাম মডেল (ডুয়াল)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## শুধুমাত্র admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## শুধুমাত্র service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common র্যাপার (ডুয়াল ও হোস্ট-নির্ভর রয়ে গেছে)

এগুলো প্রতিটি হোস্ট অ্যাপেই থাকে (প্লাগইন/কনফিগ ওয়্যারিং হোস্ট-নির্দিষ্ট):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## নিয়ম

**এক পাশ পরিবর্তন করলে অন্যটিও সিঙ্ক করতে হবে**, যতক্ষণ না একটি নিবেদিত মডেল প্যাকেজ আসে। `common\service\*` হলো `packages/platform-common`-এ একক কপি।
