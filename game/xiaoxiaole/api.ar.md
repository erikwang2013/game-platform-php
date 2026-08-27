# 田园消消乐 — واجهات الربط مع المنصة
<!-- lang-nav -->

Languages: **中文** · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · [हिन्दी](api.hi.md) · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> هذه الوثيقة هي عقد الواجهات الكامل بين لعبة «田园消消乐» ومنصة الألعاب. التقنية الطبقات في `architecture.md`، والجدولة في `plan.md`، ووظائف اللاعبين في `functional-design.md`.

---

## 1. مسار الإقلاع

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / 成就 / VIP
        │  打开 api_endpoint?session_id=&token=
        ▼
game/xiaoxiaole/  (静态资源，Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

اللعبة **واجهة أمامية ثابتة**، والجلسة الموثوقة والأموال في `service/`. يحتفظ العميل بحالة الشبكة؛ ويحتفظ الخادم بالرصيد وقوة idempotency للجولة. في المرحلة الأولى لا تُجرى مصادقة كل خطوة على الخادم، لكن يجب أن تكون طبقة المجال حتمية، بحيث يمكن في المرحلة الثانية إرسال `seed + تسلسل العمليات` إلى الخادم لإعادة الحساب.

---

## 2. قائمة الواجهات

| الواجهة | الطريقة | الاتجاه | الوصف |
|------|------|------|------|
| `/api/game/launch` | POST | المنصة → service | تشغيل جلسة اللعبة، يعيد `session_id, api_endpoint, type=self` |
| `/api/provider/balance` | GET | اللعبة → service | استعلام رصيد عملات اللعبة |
| `/api/provider/bet` | POST | اللعبة → service | خصم رسوم الدخول عند بدء المرحلة |
| `/api/provider/settle` | POST | اللعبة → service | تسوية المكافآت عند اجتياز المرحلة |
| `/api/provider/refund` | POST | اللعبة → service | استرداد الرسوم عند الخروج قبل اتخاذ أي خطوة |

تستدعي جهة اللعبة `/api/provider/*` عبر `PlatformAdapter`، بتوقيع HMAC/JWT.

---

## 3. عملية الإقلاع

1. المنصة `POST /api/game/launch` تعيد `session_id, api_endpoint, type=self`.
2. فتح `api_endpoint?session_id=&token=` (الـ token تذكرة لعبة قصيرة الأمد، أو إعادة استخدام JWT).
3. اللعبة `GET /api/provider/balance` تعرض عملات اللعبة.
4. يضغط اللاعب «بدء هذه المرحلة» ← `POST /api/provider/bet`، و`round_id = session_id + ':' + levelId + ':' + attempt`.
5. مجال `seed = hash(session_id + round_id)`.
6. عند الاجتياز `settle`، وعند الفشل لا settle؛ وعند الخروج دون عمليات `refund`.

---

## 4. رفع سجلات اللعب

`launch` (موجود) + رفع الأحداث التالية من جهة اللعبة (يمكن كتابتها أولًا إلى ClickHouse عبر `GamePlayLogService`):

| الحدث | التوقيت |
|------|------|
| `level_start` | دخول المرحلة |
| `level_win` | اجتياز المرحلة |
| `level_fail` | الفشل |
| `skill_use` | استخدام مهارة |

---

## 5. مفاتيح الميزات (FeatureFlag)

| المفتاح | الافتراضي | الوصف |
|------|------|------|
| `xxl.eco_chain` | on | سلسلة التنافس البيئي |
| `xxl.elephant` | off | قاعدة الفيل |
| `xxl.skills` | on | مهارات الأدوات الزراعية |
| `xxl.entry_bet` | off | رسوم الدخول/المحفظة |

عند الإغلاق تتدهور المرحلة إلى مطابقة ثلاثية نقية من نفس النوع، لتسهيل الإطلاق على دفعات.

---

## 6. المحفظة وقوة idempotency للجولة

- `SelfProvider::bet/settle/refund` موجودة، تستدعيها اللعبة بـ `round_id`؛ حد أقصى لمكافأة الجولة الواحدة.
- الجولة الواحدة تُنفَّذ bet/settle مرة واحدة فقط؛ الجلسة منتهية المدة تُبطل؛ النتيجة العالية الشاذة تُسجَّل فقط دون صرف تلقائي (يمكن ضبط حد أقصى للـ settle).
- الفشل لا يسترد رسوم الدخول؛ الخروج دون تبادل أي مكعب واحد → `refund`.

---

## 7. المرحلة الثانية: إعادة الحساب على الخادم

رفع تسلسل العمليات، ويشغل الخادم نفس `domain` بنسخة PHP أو عامل Node لإعادة الحساب (`seed + تسلسل العمليات` ← التحقق من الشبكة والنتيجة).
