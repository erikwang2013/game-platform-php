# शेष दोहरी-प्रति मॉडल सूची
<!-- lang-nav -->

Languages: [中文](DUAL_MODELS.md) · [English](DUAL_MODELS.en.md) · [한국어](DUAL_MODELS.ko.md) · [Русский](DUAL_MODELS.ru.md) · [Deutsch](DUAL_MODELS.de.md) · [Français](DUAL_MODELS.fr.md) · [Español](DUAL_MODELS.es.md) · [Português](DUAL_MODELS.pt.md) · **हिन्दी** · [العربية](DUAL_MODELS.ar.md) · [বাংলা](DUAL_MODELS.bn.md) · [Bahasa Indonesia](DUAL_MODELS.id.md) · [日本語](DUAL_MODELS.ja.md)


`erik/platform-common` ने केवल **common/service** निकाला है। Eloquent मॉडल और host-bound `app/common` रैपर **admin** और **service** के बीच दोहरे बने हुए हैं।

## गिनती

| पक्ष | मॉडल संख्या |
|------|-------------|
| admin `app/model` | 46 |
| service `app/model` | 44 |
| साझा नाम (अभी भी दोहरे) | 39 |

साझा-नाम वाले मॉडल अभी भी **दो प्रतियों** में मौजूद हैं। एक पक्ष बदलने पर दूसरे को भी समकालिक करना होगा, जब तक कोई मॉडल पैकेज नहीं आता।

## साझा-नाम वाले मॉडल (दोहरे)

Achievement, Announcement, CountryConfig, Coupon, DepositOrder, DeviceToken, ExchangeRecord, ExpLog, Game, GameCategory, GameCurrency, GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod, PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply, Tournament, TournamentEntry, Transaction, Translation, User, UserAchievement, UserCoupon, UserGameWallet, UserIdentity, UserOauth, UserSession, UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder

## केवल admin

- AdminRole
- AdminPermission
- AdminUser
- OperationLog
- StatDaily
- SystemConfig
- GameServer

## केवल service

- ReferralReward
- User2FA
- Friend
- ReferralCommission
- PlatformRevenue

## app/common रैपर (दोहरे रहते हैं, host-bound)

ये प्रत्येक host ऐप में रहते हैं (प्लगइन/config वायरिंग host-विशिष्ट है):

- Hashids (`HashidsService`)
- Snowflake (`SnowflakeService`)
- Encryption (`EncryptionService`)

## नियम

**एक पक्ष बदलने पर दूसरे को भी समकालिक करें** जब तक कोई समर्पित मॉडल पैकेज नहीं आता। `common\service\*` `packages/platform-common` में एकल प्रति है।
