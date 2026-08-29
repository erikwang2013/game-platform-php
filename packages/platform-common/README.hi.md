# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · **हिन्दी** · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


साझा `common\service\*`, admin/ और service/ इसे Composer path रिपॉज़िटरी के माध्यम से संदर्भित करते हैं।

## सेवाएँ

- DepositLogService — रिचार्ज ऑडिट + राजस्व/रूपांतरण
- GameDashboardService — संचालन डैशबोर्ड
- ProbabilityService — प्रायिकता विश्लेषण
- GamePlayLogService — गेम व्यवहार लॉग लेखन

निर्भरता: होस्ट द्वारा `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` प्रदान किए जाते हैं।

## एकीकरण

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## शेष दोहरी प्रतियाँ

app/model/*、app/common/*Service、अधिकांश app/service/*、EventBus अभी भी दोनों पक्षों में कॉपी किए जाते हैं।

## प्रोजेक्ट मास्कट

![प्रोजेक्ट मास्कट: डाइसी](../../docs/mascot.svg)

**डाइसी (Dicey)** — प्लेटफ़ॉर्म मास्कट। पासा गेम और संभावना-आधारित गेमप्ले को दर्शाता है, सिक्का प्लेटफ़ॉर्म अर्थव्यवस्था और मल्टी-पेमेंट गेटवे को, और बैंगनी रंग एडमिन ब्रांडिंग को दर्शाता है। SVG फ़ाइल: `docs/mascot.svg`, दस्तावेज़ों, लोगो और सामान के लिए असीमित स्केलेबल।
