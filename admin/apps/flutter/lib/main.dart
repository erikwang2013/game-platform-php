// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import 'app/theme/app_theme.dart';
import 'app/layouts/admin_layout.dart';
import 'app/pages/login/login_page.dart';
import 'app/pages/dashboard/dashboard_page.dart';
import 'app/pages/user/user_list_page.dart';
import 'app/pages/role/role_list_page.dart';
import 'app/pages/config/config_page.dart';
import 'app/pages/log/log_page.dart';
import 'app/pages/profile/profile_page.dart';
import 'app/pages/game/game_list_page.dart';
import 'app/pages/withdraw/withdraw_page.dart';
import 'app/pages/platform_user/platform_user_page.dart';
import 'app/pages/payment/payment_page.dart';
import 'app/pages/announcement/announcement_page.dart';

void main() {
  runApp(const AdminApp());
}

class AdminApp extends StatelessWidget {
  const AdminApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: '开放管理后台',
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
        GetPage(name: '/dashboard', page: () => const AdminLayout(child: DashboardPage())),
        GetPage(name: '/users', page: () => const AdminLayout(child: UserListPage(), initialIndex: 1)),
        GetPage(name: '/roles', page: () => const AdminLayout(child: RoleListPage(), initialIndex: 2)),
        GetPage(name: '/config', page: () => const AdminLayout(child: ConfigPage(), initialIndex: 3)),
        GetPage(name: '/logs', page: () => const AdminLayout(child: LogPage(), initialIndex: 4)),
        GetPage(name: '/games', page: () => const AdminLayout(child: GameListPage(), initialIndex: 5)),
        GetPage(name: '/withdraws', page: () => const AdminLayout(child: WithdrawPage(), initialIndex: 6)),
        GetPage(name: '/platform-users', page: () => const AdminLayout(child: PlatformUserPage(), initialIndex: 7)),
        GetPage(name: '/payments', page: () => const AdminLayout(child: PaymentPage(), initialIndex: 8)),
        GetPage(name: '/announcements', page: () => const AdminLayout(child: AnnouncementPage(), initialIndex: 9)),
        GetPage(name: '/profile', page: () => const ProfilePage()),
      ],
      initialRoute: '/login',
    );
  }
}
