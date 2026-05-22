// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import 'app/theme/app_theme.dart';
import 'app/i18n/locale_controller.dart';
import 'app/pages/login/login_page.dart';
import 'app/pages/game/game_hall_page.dart';
import 'app/pages/game/game_detail_page.dart';
import 'app/pages/wallet/wallet_page.dart';
import 'app/pages/wallet/deposit_page.dart';
import 'app/pages/wallet/exchange_page.dart';
import 'app/pages/wallet/withdraw_page.dart';
import 'app/pages/profile/profile_page.dart';

void main() {
  Get.put(LocaleController());
  runApp(const GamePlatformApp());
}

class GamePlatformApp extends StatelessWidget {
  const GamePlatformApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'Global Game Platform',
      locale: const Locale('en', 'US'),
      fallbackLocale: const Locale('en', 'US'),
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      builder: (context, child) => ResponsiveBreakpoints.builder(
        child: child!,
        breakpoints: [
          const Breakpoint(start: 0, end: 767, name: PHONE),
          const Breakpoint(start: 768, end: 1199, name: TABLET),
          const Breakpoint(start: 1200, end: 4500, name: DESKTOP),
        ],
      ),
      getPages: [
        GetPage(name: '/login', page: () => const LoginPage()),
        GetPage(name: '/games', page: () => const GameHallPage()),
        GetPage(name: '/game-detail', page: () => const GameDetailPage()),
        GetPage(name: '/wallet', page: () => const WalletPage()),
        GetPage(name: '/deposit', page: () => const DepositPage()),
        GetPage(name: '/exchange', page: () => const ExchangePage()),
        GetPage(name: '/withdraw', page: () => const WithdrawPage()),
        GetPage(name: '/profile', page: () => const ProfilePage()),
      ],
      initialRoute: '/login',
    );
  }
}
