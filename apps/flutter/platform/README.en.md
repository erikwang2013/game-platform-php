# game_platform — User Platform (Flutter Web)
<!-- lang-nav -->

Languages: [中文](README.md) · **English**

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


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

# Point to a backend address (default http://localhost:8792)
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8792

# Build web production bundle (outputs to build/web/)
flutter build web
```

## Usage

1. Start the backend service first: `cd service && php start.php start -d` (default port 8792)
2. Register an account and log in (username/password, OAuth, and 2FA are supported)
3. After depositing, play games with platform tokens and exchange them for game currency; game currency can be converted back to the wallet for withdrawal
4. The admin backend is in the `admin/` directory (including the Flutter web frontend `admin/apps/flutter/`)
