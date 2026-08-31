# 田园消消乐 — 平台接入 API
<!-- lang-nav -->

Languages: [中文](api.md) · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · **বাংলা** · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> এই নথিটি 《田园消消乐》 ও গেম প্ল্যাটফর্মের মধ্যে সম্পূর্ণ ইন্টারফেস চুক্তি। প্রযুক্তি স্তরবিন্যাসের জন্য `architecture.md`, সময়সূচির জন্য `plan.md`, খেলোয়াড় ফিচারের জন্য `functional-design.md` দেখুন।

---

## 1. লঞ্চ চেইন

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 成就 / VIP
        │  api_endpoint?session_id=&token= খুলুন
        ▼
game/xiaoxiaole/  (স্ট্যাটিক রিসোর্স, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

গেমটি **স্ট্যাটিক ফ্রন্টএন্ড**, কর্তৃত্বমূলক সেশন ও অর্থ `service/`-এ থাকে। ক্লায়েন্ট বোর্ড স্টেট ধরে রাখে; সার্ভার ব্যালেন্স ও round-ইডেমপোটেন্সি ধরে রাখে। প্রথম পর্বে প্রতিটি ধাপের সার্ভার-সাইড ভেরিফিকেশন নেই, তবে ডোমেইন লেয়ার অবশ্যই ডিটারমিনিস্টিক হতে হবে, যাতে দ্বিতীয় পর্বে `seed + অপারেশন সিকোয়েন্স` সার্ভারে পাঠিয়ে পুনরায় হিসাব করা যায়।

---

## 2. ইন্টারফেস তালিকা

| ইন্টারফেস | মেথড | দিক | বিবরণ |
|------|------|------|------|
| `/api/game/launch` | POST | প্ল্যাটফর্ম → service | গেম সেশন লঞ্চ, `session_id, api_endpoint, type=self` রিটার্ন করে |
| `/api/provider/balance` | GET | গেম → service | গেম কয়েন ব্যালেন্স কুয়েরি |
| `/api/provider/bet` | POST | গেম → service | লেভেল শুরুর এন্ট্রি ফি কর্তন |
| `/api/provider/settle` | POST | গেম → service | লেভেল জয়ের সেটেলমেন্ট ও পুরস্কার |
| `/api/provider/refund` | POST | গেম → service | প্রথম ধাপ না নিয়ে বের হলে ফি ফেরত |

গেম সাইড `/api/provider/*` কল করে `PlatformAdapter` দিয়ে, HMAC/JWT সিগনেচার সহ।

---

## 3. লঞ্চ ফ্লো

1. প্ল্যাটফর্ম `POST /api/game/launch` → `session_id, api_endpoint, type=self` রিটার্ন।
2. `api_endpoint?session_id=&token=` খুলুন (token হলো স্বল্পস্থায়ী গেম টিকিট, বা JWT পুনরায় ব্যবহার)।
3. গেম `GET /api/provider/balance` দিয়ে গেম কয়েন দেখায়।
4. প্লেয়ার 「এই লেভেল শুরু」 চাপলে → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`।
5. ডোমেইন `seed = hash(session_id + round_id)`।
6. জয় → `settle`, হারলে settle নেই; কোনো অপারেশন ছাড়া বের হলে `refund`।

---

## 4. Play-log রিপোর্টিং

`launch` (আগে থেকেই আছে) + গেম সাইড নিচের ইভেন্টগুলো রিপোর্ট করে (প্রথমে ClickHouse `GamePlayLogService`-এ লেখা যেতে পারে):

| ইভেন্ট | সময় |
|------|------|
| `level_start` | লেভেলে প্রবেশ |
| `level_win` | লেভেল জয় |
| `level_fail` | ব্যর্থ |
| `skill_use` | স্কিল ব্যবহার |

### `meta` ফিল্ড কন্ট্র্যাক্ট (`bet` / `settle` শেয়ার্ড, অ্যান্টি-চিট H5)

`POST /api/provider/bet` ও `POST /api/provider/settle` রিকোয়েস্ট বডির `meta` (অবজেক্ট) ফিল্ডের সংজ্ঞা:

| ফিল্ড | টাইপ | প্রয়োজন | বিবরণ |
|------|------|------|------|
| `device_id` | string | না | ডিভাইস আইডি (সার্ভারে প্লেইনটেক্সটে সংরক্ষিত, ডিভাইস-স্তরের এগ্রিগেশনের জন্য) |
| `result` | string | settle-এর জন্য প্রয়োজন | রাউন্ডের ফলাফল: `win` / `fail` |
| `move_count` | int | না | এই রাউন্ডে মুভের সংখ্যা (মুভ-ফ্রিকোয়েন্সি ডিটেকশনের ইনপুট) |
| `ended_at` | string | না | রাউন্ড শেষ হওয়ার সময় `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | না | লেভেল আইডি |
| `ip` | string | না | প্লেয়ারের উৎস IP (গেম সাইড আসল IP ফরওয়ার্ড করে; সার্ভার শুধু sha256 = `ip_hash` সংরক্ষণ করে, প্লেইনটেক্সট নয়) |
| `user_agent` | string | না | প্লেয়ারের User-Agent (সার্ভার শুধু sha256 = `user_agent_hash` সংরক্ষণ করে) |

সার্ভারে `game_game_play_log`-এ সংরক্ষণের সময়: `result / move_count / ended_at_round / device_id / level_id` আলাদা কলামে যায়; `ip` / `user_agent` হ্যাশ করে `ip_hash` / `user_agent_hash`-এ রাখা হয়; `meta`-কে `metadata` (JSON)-এ যেমন আছে তেমনই সংরক্ষণ করা হয়।

---

## 5. ফিচার সুইচ (FeatureFlag)

| সুইচ | ডিফল্ট | বিবরণ |
|------|------|------|
| `xxl.eco_chain` | on | ইকোসিস্টেম কন্ট্রোল চেইন |
| `xxl.elephant` | off | হাতির নিয়ম |
| `xxl.skills` | on | কৃষি-যন্ত্র স্কিল |
| `xxl.entry_bet` | off | এন্ট্রি ফি/ওয়ালেট |

বন্ধ থাকলে লেভেল শুদ্ধ একই-ধরনের তিন-মিলে পরিণত হয়, পর্যায়ক্রমে লঞ্চ করা সহজ হয়।

---

## 6. ওয়ালেট ও round ইডেমপোটেন্সি

- `SelfProvider::bet/settle/refund` আগে থেকেই আছে, গেম `round_id` অনুযায়ী কল করে; একক round-এর পুরস্কার সীমা নির্ধারণ করুন।
- এক round-এ শুধু একবার bet/settle হয়; টাইমআউট session বাতিল; অস্বাভাবিক উচ্চ স্কোর শুধু লগে রেকর্ড হয়, স্বয়ংক্রিয় পুরস্কার নেই (settle সীমা নির্ধারণ করা যাবে)।
- ব্যর্থ হলে এন্ট্রি ফি ফেরত নেই; একটিও টুকরো অদলবদল না করে বের হলে → `refund`।

---

## 7. দ্বিতীয় পর্ব: সার্ভার-সাইড পুনরায় হিসাব

অপারেশন সিকোয়েন্স আপলোড করুন, সার্ভার একই `domain`-এর PHP পোর্ট বা Node worker দিয়ে পুনরায় হিসাব করবে (`seed + অপারেশন সিকোয়েন্স` → বোর্ড ও স্কোর ভেরিফাই)।
