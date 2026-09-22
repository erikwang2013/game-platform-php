# ফ্রন্টএন্ড কম্পোনেন্ট আর্কিটেকচার
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · **বাংলা** · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Flutter Web কম্পোনেন্ট ট্রি

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["লগইন ফর্ম<br/>ইউজারনেম+পাসওয়ার্ড"]
    login --> captcha["ক্লিক ক্যাপচা কম্পোনেন্ট<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>ক্লিক মার্ক Circle"]

    dashboard --> sidebar["সাইডবার NavigationDrawer<br/>কোলাপ্সযোগ্য 64px/240px<br/>ড্যাশবোর্ড/ইউজার/ভূমিকা/কনফিগ/লগ"]
    dashboard --> header["টপ বার 56px<br/>কোলাপ্স বোতাম+ইউজার মেনু<br/>এক্সিট কনফার্মেশন AlertDialog"]
    dashboard --> content["কনটেন্ট এরিয়া"]

    content --> stats["পরিসংখ্যান কার্ড GridView×৪"]
    content --> chart["ট্রেন্ড লাইন চার্ট LineChart"]
    content --> pie["ডিস্ট্রিবিউশন পাই চার্ট PieChart"]
    content --> logs["সাম্প্রতিক অপারেশন ListTile×৮"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS পেজ রাউটিং

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Token নেই"| loginH["LoginPage"]
    entry -->|"Token আছে"| dashH["DashboardPage"]

    loginH -->|"লগইন সফল replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"এক্সিট কনফার্মেশন replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
