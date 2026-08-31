# 田园消消乐 — प्लेटफ़ॉर्म एकीकरण API
<!-- lang-nav -->

Languages: [中文](api.md) · [English](api.en.md) · [한국어](api.ko.md) · [Русский](api.ru.md) · [Deutsch](api.de.md) · [Français](api.fr.md) · [Español](api.es.md) · [Português](api.pt.md) · **हिन्दी** · [العربية](api.ar.md) · [বাংলা](api.bn.md) · [Bahasa Indonesia](api.id.md) · [日本語](api.ja.md)


> यह दस्तावेज़ 《田园消消乐》 और गेम प्लेटफ़ॉर्म के बीच का संपूर्ण इंटरफ़ेस अनुबंध है। तकनीकी परतों के लिए `architecture.md` देखें, शेड्यूल के लिए `plan.md`, खिलाड़ी कार्यात्मकताओं के लिए `functional-design.md`।

---

## 1. लॉन्च श्रृंखला

```
Flutter / HarmonyOS / PC Web
        │  POST /api/game/launch { game_id }
        ▼
service/ (webman :8788)
  GameController::launch  → session_id + seed + api_endpoint
  SelfProvider            → bet / settle / refund / getBalance
  GamePlayLog + EventBus  → game.played / उपलब्धियाँ / VIP
        │  api_endpoint?session_id=&token= खोलें
        ▼
game/xiaoxiaole/  (स्थिर संसाधन, Nginx)
  PlatformAdapter ──HMAC/JWT──► /api/provider/*
```

गेम एक **स्थिर फ्रंटएंड** है, आधिकारिक सत्र और धन `service/` में हैं। क्लाइंट बोर्ड स्थिति रखता है; सर्वर शेष राशि और round शक्ति-समानता रखता है। पहले चरण में प्रति-कदम सर्वर सत्यापन नहीं किया जाता, लेकिन डोमेन परत निर्धारणात्मक होनी चाहिए, ताकि दूसरे चरण में `seed + संचालन अनुक्रम` सर्वर पर भेजकर पुनर्गणना की जा सके।

---

## 2. इंटरफ़ेस सूची

| इंटरफ़ेस | विधि | दिशा | विवरण |
|------|------|------|------|
| `/api/game/launch` | POST | प्लेटफ़ॉर्म → service | गेम सत्र लॉन्च करें, `session_id, api_endpoint, type=self` लौटाता है |
| `/api/provider/balance` | GET | गेम → service | गेम कॉइन शेष राशि क्वेरी करें |
| `/api/provider/bet` | POST | गेम → service | स्तर शुरुआत पर प्रवेश शुल्क काटें |
| `/api/provider/settle` | POST | गेम → service | पास होने पर निपटान पुरस्कार |
| `/api/provider/refund` | POST | गेम → service | पहला कदम न उठाए बिना बाहर निकलने पर रिफंड |

गेम पक्ष `/api/provider/*` को `PlatformAdapter` के माध्यम से HMAC/JWT हस्ताक्षर के साथ कॉल करता है।

---

## 3. लॉन्च प्रक्रिया

1. प्लेटफ़ॉर्म `POST /api/game/launch` `session_id, api_endpoint, type=self` लौटाता है।
2. `api_endpoint?session_id=&token=` खोलें (token अल्पकालिक गेम टिकट है, या JWT पुनः उपयोग)।
3. गेम `GET /api/provider/balance` द्वारा गेम कॉइन दिखाता है।
4. खिलाड़ी 「इस स्तर की शुरुआत करें」 → `POST /api/provider/bet`, `round_id = session_id + ':' + levelId + ':' + attempt`।
5. डोमेन `seed = hash(session_id + round_id)`।
6. पास होने पर `settle`, असफल होने पर settle नहीं; बिना संचालन बाहर निकलने पर `refund`।

---

## 4. Play-log रिपोर्टिंग

`launch` (पहले से मौजूद) + गेम पक्ष निम्न इवेंट रिपोर्ट करता है (पहले ClickHouse `GamePlayLogService` में भेजा जा सकता है):

| इवेंट | समय |
|------|------|
| `level_start` | स्तर में प्रवेश |
| `level_win` | पास |
| `level_fail` | असफल |
| `skill_use` | कौशल उपयोग |

### `meta` फ़ील्ड कॉन्ट्रैक्ट (`bet` / `settle` के लिए साझा, एंटी-चीट H5)

`POST /api/provider/bet` और `POST /api/provider/settle` के रिक्वेस्ट बॉडी में `meta` (ऑब्जेक्ट) फ़ील्ड की परिभाषाएँ:

| फ़ील्ड | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| `device_id` | string | नहीं | डिवाइस ID (सर्वर पर प्लेनटेक्स्ट में संग्रहीत, डिवाइस-स्तर एग्रीगेशन के लिए) |
| `result` | string | settle के लिए आवश्यक | राउंड का परिणाम: `win` / `fail` |
| `move_count` | int | नहीं | इस राउंड में चालों की संख्या (चाल-आवृत्ति जाँच के लिए इनपुट) |
| `ended_at` | string | नहीं | राउंड समाप्ति समय `YYYY-MM-DD HH:MM:SS` |
| `level_id` | int | नहीं | लेवल ID |
| `ip` | string | नहीं | खिलाड़ी का स्रोत IP (गेम पक्ष वास्तविक IP फ़ॉरवर्ड करता है; सर्वर केवल sha256 = `ip_hash` संग्रहीत करता है, प्लेनटेक्स्ट नहीं) |
| `user_agent` | string | नहीं | खिलाड़ी का User-Agent (सर्वर केवल sha256 = `user_agent_hash` संग्रहीत करता है) |

सर्वर पर `game_game_play_log` में संग्रहण: `result / move_count / ended_at_round / device_id / level_id` अलग कॉलम में; `ip` / `user_agent` हैश करके `ip_hash` / `user_agent_hash` में; `meta` को `metadata` (JSON) में ज्यों-का-त्यों संग्रहीत किया जाता है।

---

## 5. फ़ीचर स्विच (FeatureFlag)

| स्विच | डिफ़ॉल्ट | विवरण |
|------|------|------|
| `xxl.eco_chain` | on | पारिस्थितिकी प्रतिक्रोध श्रृंखला |
| `xxl.elephant` | off | हाथी नियम |
| `xxl.skills` | on | कृषि उपकरण कौशल |
| `xxl.entry_bet` | off | प्रवेश शुल्क/वॉलेट |

बंद होने पर स्तर शुद्ध समान-प्रकार तीन-मैच में बदल जाते हैं, चरणबद्ध लॉन्च के लिए सुविधाजनक।

---

## 6. वॉलेट और round शक्ति-समानता

- `SelfProvider::bet/settle/refund` पहले से मौजूद हैं, गेम `round_id` के अनुसार कॉल करता है; प्रति round पुरस्कार ऊपरी सीमा निर्धारित करें।
- एक round केवल एक बार bet/settle होता है; टाइमआउट session अमान्य; असामान्य उच्च स्कोर केवल लॉग में दर्ज, स्वचालित पुरस्कार नहीं (settle ऊपरी सीमा सेट की जा सकती है)।
- असफल होने पर प्रवेश शुल्क वापस नहीं; बिना एक भी टुकड़ा बदले बाहर निकलने पर → `refund`।

---

## 7. दूसरा चरण: सर्वर पुनर्गणना

संचालन अनुक्रम अपलोड करें, सर्वर वही `domain` का PHP पोर्ट या Node worker चलाकर पुनर्गणना करता है (`seed + संचालन अनुक्रम` → बोर्ड और स्कोर सत्यापन)।
