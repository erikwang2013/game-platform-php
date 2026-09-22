# Frontend-Komponentenarchitektur
<!-- lang-nav -->

Languages: **中文** · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Flutter-Web-Komponentenbaum

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Anmeldeformular<br/>Benutzername + Passwort"]
    login --> captcha["Click-Captcha-Widget<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Klick markiert Circle"]

    dashboard --> sidebar["Seitenleiste NavigationDrawer<br/>einklappbar 64px/240px<br/>Dashboard/Benutzer/Rollen/Konfiguration/Protokolle"]
    dashboard --> header["Kopfleiste 56px<br/>Ein-/Ausklapp-Schaltfläche + Benutzermenü<br/>Abmeldung bestätigen AlertDialog"]
    dashboard --> content["Inhaltsbereich"]

    content --> stats["Statistik-Karten GridView×4"]
    content --> chart["Trend-Liniendiagramm LineChart"]
    content --> pie["Verteilungs-Kreisdiagramm PieChart"]
    content --> logs["Letzte Vorgänge ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS-Seitenrouting

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Kein Token"| loginH["LoginPage"]
    entry -->|"Token vorhanden"| dashH["DashboardPage"]

    loginH -->|"Anmeldung erfolgreich replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Abmeldung bestätigen replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
