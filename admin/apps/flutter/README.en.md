# admin_app — Admin Web Frontend (Flutter)
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

The admin web frontend built with Flutter 3.x, using the classic PC admin layout (sidebar + topbar + content area). It covers all management pages needed to operate the game platform: dashboard, users, roles and permissions, games, payments, withdrawals, VIP, achievements, announcements, CDN, risk control, identity verification, operation logs, and more.

## Feature List

| Module | Description |
|------|------|
| Dashboard | Platform operations overview |
| Reports | Data report summary/daily/CSV export |

| Login | Admin login (with 2FA) |
| User management | Platform user search and management |
| Platform users | User details, status and balance operations |
| Roles & permissions | Role and permission assignment |
| System config | Platform parameter configuration |
| Game management | Game list, enable/disable and categories |
| Payment management | Deposit orders, payment methods and callback logs |
| Withdrawal management | Withdrawal review and payout |
| VIP management | VIP levels and benefits configuration |
| Achievement management | Achievement definitions and progress |
| Announcement management | Announcement publishing and lifecycle |
| CDN management | CDN provider and domain configuration |
| Risk control | Risk rules and interception records |
| Identity verification | Real-name information review |
| Operation logs | Admin operation audit logs |
| Profile | Admin profile and security settings |

## Requirements

- Flutter SDK 3.x

## Installation & Running

```bash
cd admin/apps/flutter

# Install dependencies
flutter pub get

# Run in development (Chrome)
flutter run -d chrome

# Specify the backend address (default http://localhost:8787)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8787

# Build web production (output to build/web/)
flutter build web
```

## Usage

1. Start the admin backend service first: `cd admin && php start.php start -d` (default port 8787)
2. Log in with the admin account created by the install wizard (2FA supported)
3. The user-facing frontend is in `apps/flutter/platform/`, sharing the same backend service (default port 8788)
