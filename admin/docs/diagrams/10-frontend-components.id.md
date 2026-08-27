# Arsitektur Komponen Frontend
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · **Bahasa Indonesia** · [日本語](10-frontend-components.ja.md)


## Pohon Komponen Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["登录表单<br/>用户名+密码"]
    login --> captcha["点击验证码组件<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>点击标记Circle"]

    dashboard --> sidebar["侧边栏NavigationDrawer<br/>可折叠64px/240px<br/>仪表盘/用户/角色/配置/日志"]
    dashboard --> header["顶栏56px<br/>折叠按钮+用户菜单<br/>退出确认AlertDialog"]
    dashboard --> content["内容区"]

    content --> stats["统计卡片GridView×4"]
    content --> chart["趋势折线图LineChart"]
    content --> pie["分布饼图PieChart"]
    content --> logs["最近操作ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Rute Halaman HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"无Token"| loginH["LoginPage"]
    entry -->|"有Token"| dashH["DashboardPage"]

    loginH -->|"登录成功replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"退出确认replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
