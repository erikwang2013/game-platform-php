# Arquitectura de componentes del frontend
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · **Español** · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Árbol de componentes de Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Formulario de inicio de sesión<br/>Usuario + contraseña"]
    login --> captcha["Widget de captcha por clic<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>El clic marca Circle"]

    dashboard --> sidebar["Barra lateral NavigationDrawer<br/>plegable 64px/240px<br/>Panel/usuarios/roles/configuración/registros"]
    dashboard --> header["Barra superior 56px<br/>Botón de plegado + menú de usuario<br/>Confirmación de cierre AlertDialog"]
    dashboard --> content["Área de contenido"]

    content --> stats["Tarjetas de estadísticas GridView×4"]
    content --> chart["Gráfico de líneas de tendencia LineChart"]
    content --> pie["Gráfico circular de distribución PieChart"]
    content --> logs["Operaciones recientes ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Enrutado de páginas HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Sin Token"| loginH["LoginPage"]
    entry -->|"Con Token"| dashH["DashboardPage"]

    loginH -->|"Inicio de sesión correcto replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Confirmación de cierre replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
