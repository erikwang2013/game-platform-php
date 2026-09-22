# 프론트엔드 컴포넌트 아키텍처
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · **한국어** · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · [Português](10-frontend-components.pt.md) · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Flutter Web 컴포넌트 트리

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["로그인 폼<br/>사용자 이름+비밀번호"]
    login --> captcha["클릭형 캡차 컴포넌트<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>클릭 표시 Circle"]

    dashboard --> sidebar["사이드바 NavigationDrawer<br/>접이식 64px/240px<br/>대시보드/사용자/역할/설정/로그"]
    dashboard --> header["상단바 56px<br/>접기 버튼+사용자 메뉴<br/>로그아웃 확인 AlertDialog"]
    dashboard --> content["콘텐츠 영역"]

    content --> stats["통계 카드 GridView×4"]
    content --> chart["추이 꺾은선 차트 LineChart"]
    content --> pie["분포 파이 차트 PieChart"]
    content --> logs["최근 작업 ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## HarmonyOS 페이지 라우팅

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Token 없음"| loginH["LoginPage"]
    entry -->|"Token 있음"| dashH["DashboardPage"]

    loginH -->|"로그인 성공 replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"로그아웃 확인 replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
