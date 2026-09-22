# Architecture des composants frontend
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · **Français** · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Arbre des composants Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Formulaire de connexion<br/>Nom d'utilisateur+mot de passe"]
    login --> captcha["Composant de captcha à clic<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Marquage des clics Circle"]

    dashboard --> sidebar["Barre latérale NavigationDrawer<br/>Repliable 64px/240px<br/>Tableau de bord/utilisateurs/rôles/config/journaux"]
    dashboard --> header["Barre supérieure 56px<br/>Bouton de repli+menu utilisateur<br/>Confirmation de déconnexion AlertDialog"]
    dashboard --> content["Zone de contenu"]

    content --> stats["Cartes de statistiques GridView×4"]
    content --> chart["Courbe de tendance LineChart"]
    content --> pie["Camembert de répartition PieChart"]
    content --> logs["Opérations récentes ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Routage des pages HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Sans Token"| loginH["LoginPage"]
    entry -->|"Avec Token"| dashH["DashboardPage"]

    loginH -->|"Connexion réussie replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Confirmation de déconnexion replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
