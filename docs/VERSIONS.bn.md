# 版本对比
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · **বাংলা** · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## ওভারভিউ

| | বেসিক (Lite) | স্ট্যান্ডার্ড (Standard) | ফুল (Full) |
|------|------|------|------|
| ডেটা টেবিল (install.sql) | 19 | 29 | **43** (ডকুমেন্টে আগে লেখা 52 নয়) |
| API এন্ডপয়েন্ট | 38 | 54 | ~149 (admin+service, Webhook/Provider সহ) |
| ব্যাকএন্ড কন্ট্রোলার | 14 | 22 | admin 32 + service 30 |
| ডেটা মডেল | শেয়ার্ড নয় | শেয়ার্ড নয় | **admin 46 / service 44 প্রত্যেকে আলাদা, কোনো শেয়ার্ড লেয়ার নেই** |
| শেয়ার্ড সার্ভিস | কোনো শেয়ার্ড লেয়ার নেই | কোনো শেয়ার্ড লেয়ার নেই | `packages/platform-common` একক শেয়ার্ড প্যাকেজ |
| Admin ফ্রন্টএন্ড পেজ | 11 | 13 | 15 |
| Platform ফ্রন্টএন্ড পেজ | 8 | 10 | 10 |
| HarmonyOS (admin) | - | লগইন+ড্যাশবোর্ড | **৮ পেজ** `admin/apps/harmonyos/` |
| HarmonyOS (C-এন্ড) | - | - | **৫ পেজ** `apps/harmonyos/` (লগইন/গেম লবি/ডিটেইল/ওয়ালেট/আমার) |
| Docker সার্ভিস | - | - | **৭টি** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| টেস্ট কেস | 60 | 60 | admin ~132; service 3 |

---

## ইউজার অথেনটিকেশন

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| ইউজারনেম/পাসওয়ার্ড রেজিস্ট্রেশন ও লগইন | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| ক্লিক ক্যাপচা | stub | stub | ✓ poster-php |
| অ্যাকাউন্ট লক (৫ বার/১৫ মিনিট) | ✓ | ✓ | ✓ |
| সেশন সীমা (৩ কনকারেন্ট) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ ৭ প্ল্যাটফর্ম (X/MS/LinkedIn/GitHub সহ) |
| 2FA TOTP দ্বি-ফ্যাক্টর অথেনটিকেশন | - | - | ✓ |
| GDPR ডেটা এক্সপোর্ট/অ্যাকাউন্ট মুছে ফেলা | - | - | ✓ |

---

## ওয়ালেট ও ফান্ড

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| প্ল্যাটফর্ম কয়েন ওয়ালেট | ✓ | ✓ | ✓ |
| ওয়ালেট অপটিমিস্টিক লক | ✓ | ✓ | ✓ |
| লেজার রেকর্ড | ✓ | ✓ | ✓ |
| গেম কয়েন ওয়ালেট | ✓ | ✓ | ✓ |
| টপ-আপ অর্ডার তৈরি (তৈরির সাথেই checkout_url/expires_at ভরাট) | ✓ | ✓ | ✓ |
| টপ-আপ কলব্যাক অটো ক্রেডিট | - | ✓ ম্যানুয়াল | ✓ Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook ভেরিফিকেশন |
| বিনিময় কোটেশন/বাই/সেল | ✓ | ✓ | ✓ |
| বিনিময় স্প্রেড আয় | ✓ | ✓ | ✓ |
| উত্তোলন আবেদন | ✓ | ✓ | ✓ |
| গ্লোবাল উত্তোলন সুইচ | ✓ | ✓ | ✓ |
| উত্তোলন পর্যালোচনা | ✓ ম্যানুয়াল | ✓ ম্যানুয়াল | ✓ ব্যাচ+ম্যানুয়াল |
| KYC লেভেলভিত্তিক সীমা | - | ✓ ৩ লেভেল | ✓ |
| উত্তোলন ফি | - | - | ✓ |
| PDF রসিদ | - | - | ✓ |

---

## গেম ম্যানেজমেন্ট

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| গেম CRUD | ✓ | ✓ | ✓ |
| গেম কয়েন ম্যানেজমেন্ট | ✓ | ✓ | ✓ |
| C-এন্ড গেম তালিকা/ডিটেইল | ✓ | ✓ | ✓ |
| গেম লঞ্চ | ✓ | ✓ | ✓ |
| গেম ক্যাটাগরি (১০ ধরন) | - | - | ✓ |
| ক্যাটাগরি ফিল্টার | - | - | ✓ |
| গেম সার্ভার/অঞ্চল ম্যানেজমেন্ট | - | ✓ | ✓ |
| গেম রেকর্ড ট্র্যাকিং | - | ✓ | ✓ |
| ES ফুল-টেক্সট সার্চ | - | - | ✓ |
| সার্চ সাজেশন | - | - | ✓ |
| থার্ড-পার্টি গেম Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## অপারেশনাল টুলস

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| ঘোষণা ম্যানেজমেন্ট | ✓ | ✓ | ✓ |
| ড্যাশবোর্ড | ✓ অ্যাডমিন প্যানেল | ✓ অ্যাডমিন প্যানেল | ✓ অ্যাডমিন+প্ল্যাটফর্ম |
| Excel এক্সপোর্ট | ✓ | ✓ | ✓ |
| PDF এক্সপোর্ট | ✓ | ✓ | ✓ |
| ড্যাশবোর্ড বাস্তব চার্ট | - | - | ✓ fl_chart |
| কুপন সিস্টেম | - | - | ✓ |
| লিডারবোর্ড (দৈনিক/সাপ্তাহিক/মাসিক/সর্বকালীন) | - | - | ✓ Redis ক্যাশ |
| WebSocket রিয়েল-টাইম লিডারবোর্ড | - | - | ✓ পোর্ট 8789 |
| নোটিফিকেশন সিস্টেম (ইন-অ্যাপ+ইমেইল) | - | - | ✓ |
| রেফারেল কমিশন | - | - | ✓ |
| দৈনিক পরিসংখ্যান স্ন্যাপশট | - | ✓ | ✓ |
| প্ল্যাটফর্ম রাজস্ব ট্র্যাকিং | - | - | ✓ |

---

## নিরাপত্তা ও কমপ্লায়েন্স

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| ১৮ স্তর গভীর প্রতিরক্ষা | ✓ | ✓ | ✓ |
| RBAC পারমিশন কন্ট্রোল | ✓ | ✓ | ✓ |
| অপারেশন অডিট লগ | ✓ | ✓ | ✓ |
| ৮ প্ল্যাটফর্ম উৎস ডিটেকশন | ✓ | ✓ | ✓ |
| Redis স্লাইডিং উইন্ডো রেট লিমিট | ✓ | ✓ | ✓ |
| KYC রিয়েল-নেম ভেরিফিকেশন | - | ✓ | ✓ |
| রিস্ক কন্ট্রোল ইঞ্জিন (৪ নিয়ম) | - | ✓ | ✓ |
| পেমেন্ট কলব্যাক ভেরিফিকেশন | - | - | ✓ |

---

## ইন্টারন্যাশনালাইজেশন

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| বহুভাষা সাপোর্ট | চীনা/ইংরেজি | ৪ ভাষা | ৪ ভাষা |
| অনুবাদ টেবিল+ক্যাশ | ✓ | ✓ | ✓ |
| ভাষা অটো ডিটেকশন | ✓ | ✓ | ✓ |
| দেশভেদে কনফিগ | - | - | ✓ ৮ দেশ |

---

## ডিপ্লয়মেন্ট ও অপারেশন

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| webman স্বতন্ত্র ডিপ্লয় | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ ৭ সার্ভিস |
| Nginx রিভার্স প্রক্সি | - | - | ✓ |
| Crontab শিডিউলড টাস্ক | - | ✓ | ✓ |
| Prometheus মনিটরিং | ✓ | ✓ | ✓ `/metrics` ব্যবসায়িক gauge + ইভেন্ট counter |
| হেলথ চেক | ✓ | ✓ | ✓ |
| hg/apidoc অনলাইন ডক | - | - | ✓ ৪১ কন্ট্রোলার |

---

## ক্লায়েন্ট

| ফিচার | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| Flutter Web PC অ্যাডমিন প্যানেল | ✓ ৫ পেজ | ✓ ১১ পেজ | ✓ ১৫ পেজ |
| Flutter Web PC ইউজার প্ল্যাটফর্ম | ✓ ৫ পেজ | ✓ ৮ পেজ | ✓ ১০ পেজ |
| HarmonyOS admin | - | ✓ লগইন+ড্যাশবোর্ড | ✓ ৮ পেজ `admin/apps/harmonyos/` |
| HarmonyOS C-এন্ড | - | - | ✓ ৫ পেজ `apps/harmonyos/` |

---

## ডেটাবেস টেবিল

### বেসিক (১৯টি)
```
管理后台 (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

平台核心 (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### স্ট্যান্ডার্ডে নতুন (১০টি)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### ফুলে নতুন (১৩টি)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## API এন্ডপয়েন্ট

| মডিউল | বেসিক | স্ট্যান্ডার্ড | ফুল |
|------|--------|--------|--------|
| অথেনটিকেশন | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| ওয়ালেট | 2 | 2 | 3 (+টপ-আপ কলব্যাক) |
| বিনিময় | 4 | 4 | 4 |
| উত্তোলন | 2 | 2 | 8 (+ব্যাচ+সীমা+রিভিউ) |
| গেম | 3 | 4 | 7 (+সার্ভার+রেকর্ড+সার্চ) |
| ব্যবহারকারী | 2 | 2 | 7 (+KYC+GDPR+প্রাইভেসি) |
| অ্যাডমিন প্যানেল | 18 | 25 | 79 |
| অপারেশনাল টুলস | - | - | 30 (+লিডারবোর্ড+কুপন+নোটিফিকেশন+রেফারেল) |
| ইন্টারন্যাশনালাইজেশন | 2 | 2 | 4 (+দেশ কনফিগ) |
| **মোট** | **38** | **54** | **129** |

---

## ইকোসিস্টেম এক্সটেনশন (v2.0) — নতুন

| ফিচার | বিবরণ |
|------|------|
| GameProvider অ্যাবস্ট্রাকশন লেয়ার | SelfProvider (DB ট্রানজেকশন) + ThirdPartyProvider (HTTP+সিগনেচার) |
| Provider API গেটওয়ে | balance/bet/settle/refund কলব্যাক + ProviderAuth মিডলওয়্যার |
| টিকিট সিস্টেম | C-এন্ড তৈরি/রিপ্লাই + অ্যাডমিন প্রসেস/অ্যাসাইন/ক্লোজ |
| ইমেইল ভেরিফিকেশন | ৬ সংখ্যার কোড, Redis ১০ মিনিট মেয়াদ, ৬০ সেকেন্ড রিসেন্ড সীমা |
| পুশ নোটিফিকেশন | PushService (FCM/APNs/হুয়াওয়ে পুশ) |
| VIP সিস্টেম | ৫ লেভেল, অভিজ্ঞতা পয়েন্ট সঞ্চয়, অটো আপগ্রেড, বিনিময় ডিসকাউন্ট, উত্তোলন ছাড়, এক্সচেঞ্জ রেট বোনাস |
| অ্যাচিভমেন্ট সিস্টেম | ১২টি বিল্ট-ইন অ্যাচিভমেন্ট, ইভেন্ট-চালিত ডিটেকশন, প্রগ্রেস ট্র্যাকিং |
| ফ্রেন্ড সিস্টেম | রিকোয়েস্ট/অ্যাকসেপ্ট/রিজেক্ট/ডিলিট/সার্চ |
| প্রাইভেট মেসেজ/চ্যাট | REST + WebSocket রিয়েল-টাইম মেসেজ (পোর্ট 8790) |
| ইভেন্ট বাস | Redis Pub/Sub; emit INCR `metrics:event_*`; কনজিউম প্রসেস `EventConsumer` বাস্তবায়িত |
| ফিচার সুইচ | FeatureFlag DB-ভিত্তিক; `inRollout`/`abTest` `feature.{name}_percent` পড়ে |
| Webhook | - | - | ✓ ৭ ধরনের ইভেন্ট+Pub/Sub ডেলিভারি |
| চ্যাট | - | - | ✓ REST+WebSocket :8791 |
| টুর্নামেন্ট সিস্টেম | - | - | ✓ FeatureFlag+tournament |
| কুপন শর্ত | - | - | ✓ min_deposit/first_user/game_id |
| মাল্টি-লেভেল কমিশন | - | - | ✓ দ্বি-স্তর প্রফিট শেয়ার |
| SDK ডকুমেন্টেশন | - | - | ✓ PHP/Go/Python |
| অ্যাডভান্সড অ্যানালিটিক্স | রিটেনশন/D1-D30, কনভার্সন ফানেল, ARPU/ARPPU |

### নতুন ডেটা টেবিল (১০টি)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### নতুন Provider API এন্ডপয়েন্ট (৪টি)
```
POST /api/provider/balance  — 查询余额
POST /api/provider/bet      — 通知下注
POST /api/provider/settle   — 通知结算
POST /api/provider/refund   — 通知退款
```

### নতুন C-এন্ড API এন্ডপয়েন্ট (৮টি)
```
POST /api/verify/send-email    — 发送邮箱验证码
POST /api/verify/confirm-email — 确认邮箱
GET  /api/ticket/list             — 工单列表
POST /api/ticket/create           — 创建工单
GET  /api/ticket/{id}             — 工单详情
POST /api/ticket/{id}/reply       — 回复工单
GET  /api/user/vip-status         — VIP状态
GET  /api/user/achievements       — 成就列表
```

### নতুন অ্যাডমিন প্যানেল API এন্ডপয়েন্ট (৬টি)
```
GET  /admin/ticket/list          — 工单列表
GET  /admin/ticket/{id}          — 工单详情
POST /admin/ticket/{id}/reply    — 回复工单
POST /admin/ticket/{id}/close    — 关闭工单
POST /admin/ticket/{id}/assign   — 指定处理人
GET  /admin/analytics/retention  — 留存分析
GET  /admin/analytics/funnel     — 转化漏斗
GET  /admin/analytics/arpu       — ARPU趋势
GET  /admin/analytics/economy    — 经济指标
```
