# Mise en page Flutter multipateforme style PC — spécification de conception
<!-- lang-nav -->

Languages: [中文](2026-05-18-flutter-multiplatform-pc-layout-design.md) · [English](2026-05-18-flutter-multiplatform-pc-layout-design.en.md) · [한국어](2026-05-18-flutter-multiplatform-pc-layout-design.ko.md) · [Русский](2026-05-18-flutter-multiplatform-pc-layout-design.ru.md) · [Deutsch](2026-05-18-flutter-multiplatform-pc-layout-design.de.md) · **Français** · [Español](2026-05-18-flutter-multiplatform-pc-layout-design.es.md) · [Português](2026-05-18-flutter-multiplatform-pc-layout-design.pt.md) · [हिन्दी](2026-05-18-flutter-multiplatform-pc-layout-design.hi.md) · [العربية](2026-05-18-flutter-multiplatform-pc-layout-design.ar.md) · [বাংলা](2026-05-18-flutter-multiplatform-pc-layout-design.bn.md) · [Bahasa Indonesia](2026-05-18-flutter-multiplatform-pc-layout-design.id.md) · [日本語](2026-05-18-flutter-multiplatform-pc-layout-design.ja.md)


Date : 2026-05-18

## Objectif

Activer les plateformes desktop macOS et Windows, garantir que toutes les plateformes iOS (iPhone + iPad), macOS, Windows et Linux utilisent une mise en page de style PC pour backend d'administration (barre latérale + barre supérieure + zone de contenu), et adapter les téléphones avec un menu tiroir.

## Stratégie de plateformes

| Plateforme | Statut | Description |
|------|------|------|
| Linux | Activé | Aucune action requise |
| macOS | À activer | `flutter config --enable-macos-desktop` |
| Windows | À activer | `flutter config --enable-windows-desktop` |
| iOS | Déjà présent | Couvre à la fois iPhone (mise en page mobile) et iPad (mise en page desktop) |
| Web | Déjà présent | Aucune action requise |

L'iPad n'a pas de cible de plateforme dédiée : il atteint la mise en page desktop via le palier TABLET des points de rupture réactifs.

## Points de rupture réactifs

| Point de rupture | Plage | Mode de mise en page |
|------|------|----------|
| PHONE | 0 - 767 | Menu tiroir (AppBar + Drawer) |
| TABLET | 768 - 1199 | Barre latérale repliable (repliée par défaut à 64px) |
| DESKTOP | 1200 - 2460 | Barre latérale (dépliée par défaut à 240px) |

La largeur minimale de l'iPad en portrait est 768px : il atteint TABLET et obtient la barre latérale.
Tous les iPhone mesurent moins de 768px de large : ils atteignent PHONE et obtiennent le menu tiroir.

## Modifications de fichiers

### 1. main.dart — configuration des points de rupture

- `PHONE`: 0-767, `TABLET`: 768-1199, `DESKTOP`: 1200-2460
- Le reste du code reste inchangé

### 2. admin_layout.dart — bascule de navigation réactive

- `_isPhone`: atteint le point de rupture PHONE
- `_buildPhoneLayout()`: Scaffold + AppBar + Drawer, le NavigationDrawer du Drawer réutilise les mêmes éléments de menu que la barre latérale desktop
- `_buildDesktopLayout()`: mise en page Row existante (barre latérale + barre supérieure + zone de contenu)
- En TABLET, la barre latérale est repliée par défaut ; en DESKTOP, dépliée par défaut

### 3. app_theme.dart — complément du thème sombre

- Extraction des styles de composants en constantes privées `_dataTableTheme`, `_cardTheme`, `_inputDecorationTheme`, `_dividerTheme`
- Les thèmes clair et sombre réutilisent le même jeu de styles de composants
- Le thème sombre est complété avec Material 3 + même seed + luminosité dark
