# फ्रंटएंड कंपोनेंट आर्किटेक्चर
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · **हिन्दी** · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Flutter Web कंपोनेंट ट्री

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["लॉगिन फ़ॉर्म<br/>उपयोगकर्ता नाम+पासवर्ड"]
    login --> captcha["क्लिक कैप्चा कंपोनेंट<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>क्लिक मार्कर Circle"]

    dashboard --> sidebar["साइडबार NavigationDrawer<br/>संक्षेपणीय 64px/240px<br/>डैशबोर्ड/उपयोगकर्ता/भूमिका/कॉन्फ़िग/लॉग"]
    dashboard --> header["टॉपबार 56px<br/>संक्षेपण बटन+उपयोगकर्ता मेनू<br/>लॉगआउट पुष्टि AlertDialog"]
    dashboard --> content["सामग्री क्षेत्र"]

    content --> stats["सांख्यिकी कार्ड GridView×4"]
    content --> chart["प्रवृत्ति लाइन चार्ट LineChart"]
    content --> pie["वितरण पाई चार्ट PieChart"]
    content --> logs["हाल के ऑपरेशन ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS पेज रूटिंग

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Token नहीं"| loginH["LoginPage"]
    entry -->|"Token है"| dashH["DashboardPage"]

    loginH -->|"लॉगिन सफल replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"लॉगआउट पुष्टि replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
