# フロントエンドコンポーネントアーキテクチャ
<!-- lang-nav -->

Languages: **中文** · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Flutter Web コンポーネントツリー

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["ログインフォーム<br/>ユーザー名+パスワード"]
    login --> captcha["クリック検証コードコンポーネント<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>クリックマークCircle"]

    dashboard --> sidebar["サイドバーNavigationDrawer<br/>折りたたみ可64px/240px<br/>ダッシュボード/ユーザー/ロール/設定/ログ"]
    dashboard --> header["ヘッダーバー56px<br/>折りたたみボタン+ユーザーメニュー<br/>ログアウト確認AlertDialog"]
    dashboard --> content["コンテンツ領域"]

    content --> stats["統計カードGridView×4"]
    content --> chart["トレンド折れ線グラフLineChart"]
    content --> pie["分布円グラフPieChart"]
    content --> logs["最近の操作ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS ページルーティング

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Token なし"| loginH["LoginPage"]
    entry -->|"Token あり"| dashH["DashboardPage"]

    loginH -->|"ログイン成功replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"ログアウト確認replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
