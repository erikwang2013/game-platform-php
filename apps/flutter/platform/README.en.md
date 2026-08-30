# game_platform — User Platform (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

The web frontend of the C-side user platform, built with Flutter 3.x, offering users the full game aggregation platform experience: registration and login, game lobby, wallet, deposit, withdrawal, exchange, leaderboards, coupons, notifications, chat, friends and support tickets.

## Feature List

| Module | Description |
|------|------|
| Login/Register | Username/password / OAuth / 2FA |
| Game Lobby | Game list/categories/search |
| Wallet | Platform token/game currency balances and transactions |
| Deposit | Choose a payment method, redirect to gateway payment |
| Withdrawal | Apply for withdrawal, review status tracking |
| Exchange | Real-time platform token ⇄ game currency exchange |
| Leaderboards | Daily/weekly/monthly/all-time |
| Coupons | Claim and use |
| Notifications | In-app messages (deposit/withdrawal/coupons, etc.) |
| Chat | WebSocket real-time messaging |
| Friends | Friend system |
| Tickets | Create and reply to support tickets |
| Profile | Profile editing/security settings |

## Requirements

- Flutter SDK 3.x

## Installation & Run

```bash
cd apps/flutter/platform

# Install dependencies
flutter pub get

# Run in development (Chrome)
flutter run -d chrome

# Point to a backend address (default http://localhost:8788)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8788

# Build web production bundle (outputs to build/web/)
flutter build web
```

## Usage

1. Start the backend service first: `cd service && php start.php start -d` (default port 8788)
2. Register an account and log in (username/password, OAuth, and 2FA are supported)
3. After depositing, play games with platform tokens and exchange them for game currency; game currency can be converted back to the wallet for withdrawal
4. The admin backend is in the `admin/` directory (including the Flutter web frontend `admin/apps/flutter/`)
