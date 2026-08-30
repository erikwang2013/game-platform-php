# admin_app — Admin-Web-Frontend (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · **Deutsch** · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Das Admin-Web-Frontend auf Basis von Flutter 3.x mit klassischem PC-Admin-Layout (Sidebar + Topbar + Inhaltsbereich). Es deckt alle Verwaltungsseiten ab, die für den Betrieb der Gaming-Plattform nötig sind: Dashboard, Benutzer, Rollen und Berechtigungen, Spiele, Zahlungen, Auszahlungen, VIP, Erfolge, Ankündigungen, CDN, Risikomanagement, Identitätsprüfung, Operationsprotokolle und mehr.

## Funktionsübersicht

| Modul | Beschreibung |
|------|------|
| Dashboard | Gesamtübersicht der Plattform-Kennzahlen |
| Berichte | Datenbericht-Zusammenfassung/Tagesbericht/CSV |

| Anmeldung | Admin-Login (mit 2FA) |
| Benutzerverwaltung | Suche und Verwaltung der Plattform-Benutzer |
| Plattform-Benutzer | Benutzerdetails, Status und Kontostand-Operationen |
| Rollen & Berechtigungen | Rollen- und Berechtigungszuweisung |
| Systemkonfiguration | Parameter der Plattform konfigurieren |
| Spielverwaltung | Spielliste, Veröffentlichung/Stopp und Kategorien |
| Zahlungsverwaltung | Einzahlungsaufträge, Zahlungsmethoden und Callback-Logs |
| Auszahlungsverwaltung | Auszahlungsprüfung und -auszahlung |
| VIP-Verwaltung | VIP-Stufen und Vorteile konfigurieren |
| Erfolgsverwaltung | Erfolgsdefinitionen und Fortschritt ansehen |
| Ankündigungsverwaltung | Ankündigungen veröffentlichen und stoppen |
| CDN-Verwaltung | CDN-Anbieter und Domains konfigurieren |
| Risikomanagement | Risikoregeln und Sperrprotokolle |
| Identitätsprüfung | Prüfung der Realname-Daten |
| Operationsprotokoll | Audit-Log der Admin-Aktionen |
| Profil | Admin-Profil und Sicherheitseinstellungen |

## Anforderungen

- Flutter SDK 3.x

## Installation und Ausführung

```bash
cd admin/apps/flutter

# Abhängigkeiten installieren
flutter pub get

# Entwicklung ausführen (Chrome)
flutter run -d chrome

# Backend-Adresse festlegen (Standard http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Web-Produktionsbuild (Ausgabe nach build/web/)
flutter build web
```

## Verwendung

1. Zuerst den Admin-Backend-Dienst starten: `cd admin && php start.php start -d` (Standardport 8787)
2. Mit dem vom Installationsassistenten erstellten Admin-Konto anmelden (2FA wird unterstützt)
3. Das Benutzer-Frontend befindet sich in `apps/flutter/platform/` und nutzt denselben Backend-Dienst (Standardport 8788)
