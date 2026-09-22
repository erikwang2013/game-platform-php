# Архитектура фронтенд-компонентов
<!-- lang-nav -->

Languages: **中文** · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Дерево компонентов Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Форма входа<br/>Имя пользователя+пароль"]
    login --> captcha["Компонент клик-капчи<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Circle для метки клика"]

    dashboard --> sidebar["Боковая панель NavigationDrawer<br/>Сворачиваемая 64px/240px<br/>Дашборд/Пользователи/Роли/Конфигурация/Логи"]
    dashboard --> header["Верхняя панель 56px<br/>Кнопка сворачивания+меню пользователя<br/>AlertDialog подтверждения выхода"]
    dashboard --> content["Область контента"]

    content --> stats["Карточки статистики GridView×4"]
    content --> chart["График тренда LineChart"]
    content --> pie["Круговая диаграмма PieChart"]
    content --> logs["Последние операции ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Маршрутизация страниц HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Нет Token"| loginH["LoginPage"]
    entry -->|"Есть Token"| dashH["DashboardPage"]

    loginH -->|"Успешный вход replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Подтверждение выхода replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
