# Arquitetura de Componentes do Frontend
<!-- lang-nav -->

Languages: [中文](10-frontend-components.md) · [English](10-frontend-components.en.md) · [한국어](10-frontend-components.ko.md) · [Русский](10-frontend-components.ru.md) · [Deutsch](10-frontend-components.de.md) · [Français](10-frontend-components.fr.md) · [Español](10-frontend-components.es.md) · **Português** · [हिन्दी](10-frontend-components.hi.md) · [العربية](10-frontend-components.ar.md) · [বাংলা](10-frontend-components.bn.md) · [Bahasa Indonesia](10-frontend-components.id.md) · [日本語](10-frontend-components.ja.md)


## Árvore de Componentes Flutter Web

```mermaid
flowchart TD
    app["AdminApp(GetMaterialApp)"]

    app --> login["/login<br/>LoginPage"]
    app --> dashboard["/dashboard<br/>AdminLayout"]

    login --> form["Formulário de login<br/>Usuário + senha"]
    login --> captcha["Componente de captcha por clique<br/>GestureDetector+Stack<br/>Image.memory(base64)<br/>Marca de clique Circle"]

    dashboard --> sidebar["Barra lateral NavigationDrawer<br/>Recolhível 64px/240px<br/>Painel/Usuário/Função/Configuração/Log"]
    dashboard --> header["Barra superior 56px<br/>Botão de recolher + menu do usuário<br/>Confirmação de saída AlertDialog"]
    dashboard --> content["Área de conteúdo"]

    content --> stats["Cartões de estatística GridView×4"]
    content --> chart["Gráfico de linha de tendência LineChart"]
    content --> pie["Gráfico de pizza de distribuição PieChart"]
    content --> logs["Operações recentes ListTile×8"]

    style app fill:#1677FF,color:#fff
    style captcha fill:#FA8C16,color:#fff
    style sidebar fill:#722ED1,color:#fff
```

## Rotas de Páginas HarmonyOS

```mermaid
flowchart LR
    entry["EntryAbility"]
    entry -->|"Sem Token"| loginH["LoginPage"]
    entry -->|"Com Token"| dashH["DashboardPage"]

    loginH -->|"Login bem-sucedido replaceUrl"| dashH

    dashH -->|"pushUrl"| userList["UserListPage"]
    dashH -->|"pushUrl"| profile["ProfilePage"]

    userList -->|"pushUrl"| userDetail["UserDetailPage"]
    userList -->|"router.back"| dashH
    userDetail -->|"router.back"| userList

    profile -->|"Confirmação de saída replaceUrl"| loginH
    profile -->|"router.back"| dashH

    style loginH fill:#1677FF,color:#fff
    style dashH fill:#52C41A,color:#fff
    style userList fill:#FA8C16,color:#fff
    style userDetail fill:#FA8C16,color:#fff
    style profile fill:#722ED1,color:#fff
```
