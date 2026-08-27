# Layout Flutter multiplataforma estilo PC — Especificação de design
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · [Français](2026-05-18-flutter-multiplatform-pc-layout-design.fr.md) · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · **Português** · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Data: 2026-05-18

## Objetivo

Habilitar as plataformas de desktop macOS e Windows, garantindo que todas as plataformas — iOS (iPhone + iPad), macOS, Windows, Linux — usem o layout estilo PC de painel administrativo (sidebar + topbar + área de conteúdo), com menu drawer no celular.

## Estratégia de plataformas

| Plataforma | Status | Observação |
|------|------|------|
| Linux | Já habilitado | Nenhuma ação necessária |
| macOS | Precisa habilitar | `flutter config --enable-macos-desktop` |
| Windows | Precisa habilitar | `flutter config --enable-windows-desktop` |
| iOS | Já existe | Cobre tanto iPhone (layout mobile) quanto iPad (layout desktop) |
| Web | Já existe | Nenhuma ação necessária |

O iPad não possui alvo de plataforma separado; ele atinge o layout desktop por meio do breakpoint responsivo TABLET.

## Breakpoints responsivos

| Breakpoint | Faixa | Modo de layout |
|------|------|----------|
| PHONE | 0 - 767 | Menu drawer (AppBar + Drawer) |
| TABLET | 768 - 1199 | Sidebar recolhível (padrão recolhida 64px) |
| DESKTOP | 1200 - 2460 | Sidebar (padrão expandida 240px) |

A largura mínima do iPad em retrato é 768px, atingindo TABLET, obtendo o layout com sidebar.
As larguras do iPhone são todas menores que 768px, atingindo PHONE, obtendo o menu drawer.

## Alterações de arquivos

### 1. main.dart — Configuração de breakpoints

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- O restante do código permanece inalterado

### 2. admin_layout.dart — Alternância de navegação responsiva

- `_isPhone`: atinge o breakpoint PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer; o NavigationDrawer dentro do Drawer reutiliza os mesmos itens de menu da sidebar desktop
- `_buildDesktopLayout()`: layout Row existente (sidebar + topbar + área de conteúdo)
- No TABLET a sidebar fica recolhida por padrão; no DESKTOP, expandida por padrão

### 3. app_theme.dart — Complemento do tema escuro

- Extrair os estilos de componentes como constantes privadas `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Temas claro e escuro reutilizam o mesmo conjunto de estilos de componentes
- O tema escuro usa Material 3 + o mesmo seed + luminosidade dark
