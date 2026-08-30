# game_platform — Benutzerplattform (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Das Web-Frontend der Benutzerplattform (C-Seite), basierend auf Flutter 3.x, bietet Nutzern das vollständige Erlebnis der Spiel-Aggregationsplattform: Registrierung und Login, Spiellobby, Wallet, Einzahlung, Auszahlung, Tausch, Ranglisten, Gutscheine, Benachrichtigungen, Chat, Freunde und Support-Tickets.

## Funktionsübersicht

| Modul | Beschreibung |
|------|------|
| Login/Registrierung | Benutzername/Passwort / OAuth / 2FA |
| Spiellobby | Spielliste/Kategorien/Suche |
| Wallet | Guthaben und Transaktionen für Plattform-Token/Spielwährung |
| Einzahlung | Zahlungsmethode wählen, Weiterleitung zur Gateway-Zahlung |
| Auszahlung | Auszahlung beantragen, Status verfolgen |
| Tausch | Echtzeit-Tausch Plattform-Token ⇄ Spielwährung |
| Ranglisten | Tages-/Wochen-/Monats-/Gesamt |
| Gutscheine | Einlösen und verwenden |
| Benachrichtigungen | In-App-Nachrichten (Einzahlung/Auszahlung/Gutscheine usw.) |
| Chat | WebSocket-Echtzeitnachrichten |
| Freunde | Freundessystem |
| Tickets | Support-Tickets erstellen und beantworten |
| Profil | Profil bearbeiten/Sicherheitseinstellungen |

## Anforderungen

- Flutter SDK 3.x

## Installation und Ausführung

```bash
cd apps/flutter/platform

# Abhängigkeiten installieren
flutter pub get

# Entwicklung ausführen (Chrome)
flutter run -d chrome

# Backend-Adresse angeben (Standard http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Web-Produktions-Build (Ausgabe in build/web/)
flutter build web
```

## Verwendung

1. Zuerst das Backend starten: `cd service && php start.php start -d` (Standardport 8788)
2. Konto registrieren und anmelden (Benutzername/Passwort, OAuth und 2FA werden unterstützt)
3. Nach der Einzahlung mit Plattform-Tokens spielen und in Spielwährung tauschen; Spielwährung kann zurück ins Wallet gewechselt und ausgezahlt werden
4. Das Admin-Backend befindet sich im Verzeichnis `admin/` (einschließlich Flutter-Web-Frontend `admin/apps/flutter/`)
