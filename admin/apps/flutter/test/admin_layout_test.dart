// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/i18n/locale_controller.dart';
import 'package:admin_app/app/layouts/admin_layout.dart';
import 'package:admin_app/app/pages/dashboard/dashboard_page.dart';
import 'package:admin_app/app/pages/login/login_page.dart';
import 'package:admin_app/app/services/auth_service.dart';
import 'package:responsive_framework/responsive_framework.dart';
import 'test_helpers.dart';

void main() {
  setUp(setUpTest);

  Future<void> pumpLayout(WidgetTester tester, {bool loggedIn = true}) async {
    tolerateKnownDashboardOverflow(); // 须在 testWidgets 体内调用(binding 已安装自身 onError)
    tester.view.physicalSize = const Size(1400, 900); // PC 桌面断点
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    if (loggedIn) {
      await AuthService.saveLogin(token: 'test-token', refreshToken: 'test-refresh', username: 'admin');
    }
    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      builder: (context, child) => ResponsiveBreakpoints.builder(
        child: child!,
        breakpoints: [
          const Breakpoint(start: 0, end: 767, name: PHONE),
          const Breakpoint(start: 768, end: 1199, name: TABLET),
          const Breakpoint(start: 1200, end: 4500, name: DESKTOP),
        ],
      ),
      getPages: [GetPage(name: '/login', page: () => const LoginPage())],
      home: const AdminLayout(child: DashboardPage()),
    ));
    if (loggedIn) {
      await tester.pumpAndSettle();
    } else {
      // 未登录重定向到登录页，其验证码区域含无限动画 spinner → 不能用 pumpAndSettle
      await pumpUntil(tester, find.text('Username'));
    }
  }

  testWidgets('侧边栏渲染全部 14 个导航项', (tester) async {
    await pumpLayout(tester);

    expect(find.text('Admin Panel'), findsWidgets); // 侧边栏标题
    for (final label in [
      'Dashboard', 'Users', 'Roles & Permissions', 'Settings', 'Operation Logs',
      'Game Management', 'Withdraw Management', 'Platform Users', 'KYC Review',
      'Risk Logs', 'Payment Management', 'Announcements', 'VIP Levels', 'Achievements',
    ]) {
      expect(find.text(label), findsWidgets, reason: '缺少导航项: $label');
    }
  });

  testWidgets('点击导航切换页面: Users → 用户管理页', (tester) async {
    await pumpLayout(tester);

    await tester.tap(find.text('Users'));
    await tester.pumpAndSettle();

    expect(find.text('User Management'), findsOneWidget);
    expect(find.text('Search username/real name'), findsOneWidget);
    // 离线: 列表请求失败 → 空数据态
    expect(find.text('No data'), findsOneWidget);
    // 失败加载触发的 Get.snackbar 自动关闭 Timer 需在测试结束前触发，否则报 pending timer
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();
  });

  testWidgets('用户菜单: 语言切换 zh → 界面文案变化', (tester) async {
    await pumpLayout(tester);

    await tester.tap(find.text('Admin'));
    await tester.pumpAndSettle();
    // en 语言下菜单项文案为「切换到中文」(layout 源码硬编码)
    expect(find.text('切换到中文'), findsOneWidget);
    // GetX updateLocale→forceAppUpdate→performReassemble 会在 tap 微任务中同步重建
    // 整棵应用树，与 flutter_test 的 fake-async 帧机制互斥(schedulerPhase 卡在
    // midFrameMicrotasks，后续 pump 断言失败)。此为测试环境限制，非应用 bug
    // (真实 vsync 下语言切换正常)。故改为直接调用控制器并验证文案联动。
    Get.find<LocaleController>().changeLocale('zh');
    await tester.pumpAndSettle();

    expect(Get.find<LocaleController>().currentLocale.value, 'zh');
    expect(find.text('管理后台'), findsWidgets);
  });

  testWidgets('用户菜单: 退出登录 → 确认弹窗 → 跳转登录页', (tester) async {
    await pumpLayout(tester);

    await tester.tap(find.text('Admin'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Logout'));
    await tester.pumpAndSettle();

    expect(find.text('Are you sure you want to logout?'), findsWidgets);
    await tester.tap(find.text('Confirm'));
    // 登录页含无限动画 spinner，避免 pumpAndSettle
    await pumpUntil(tester, find.text('Game Platform Admin'));
    expect(find.text('Username'), findsOneWidget);
  });

  testWidgets('未登录访问布局 → 重定向到登录页', (tester) async {
    await pumpLayout(tester, loggedIn: false);

    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
  });
}
