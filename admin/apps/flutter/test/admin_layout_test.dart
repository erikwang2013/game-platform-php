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

  /// [height] 默认 900（PC 断点）。**要断言抽屉里的全部导航项时必须调高**：
  /// 抽屉是懒构建的，24 项在 900 高下只建到第 ~14 项，后面的 `find.text` 恒查不到
  /// （这正是旧用例「14 个导航项」红掉的根因——它断言的第 15 项 `VIP Levels` 根本没进树）。
  Future<void> pumpLayout(WidgetTester tester, {bool loggedIn = true, double height = 900}) async {
    tolerateKnownDashboardOverflow(); // 须在 testWidgets 体内调用(binding 已安装自身 onError)
    tester.view.physicalSize = Size(1400, height); // PC 桌面断点
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

  /// 侧栏**模块名**必须全部走 i18n。
  ///
  /// 这条替换了旧用例「侧边栏渲染全部 14 个导航项」。旧用例有两个毛病：
  /// ① 写死 14 个英文串，导航长到 24 项后既过时又漏检；
  /// ② 更根本的是**断言方式错**——抽屉懒构建，900 高下只建到第 ~14 项，
  ///    第 15 项 `VIP Levels` 根本没进树，于是它从「清单过时」退化成「恒红」。
  ///
  /// 新判据不依赖具体文案、也不会随导航增删而失效：在 en 下取抽屉里所有 Text，
  /// **既不许出现汉字**（= 有模块名硬编码没走 t()，本次用户报的就是这个：
  /// `风控大盘` / `运营活动` 两项写死在 `admin_layout.dart`），
  /// **也不许出现键形状的串**（= 存了键但渲染处忘了 t()，界面会直接露 `nav.xxx`）。
  testWidgets('侧边栏模块名全部走 i18n（en 下无汉字、无裸露键名）', (tester) async {
    await pumpLayout(tester, height: 2000); // 调高：让 24 项全部进树（见 pumpLayout 注释）

    expect(find.text('Admin Panel'), findsWidgets); // 侧边栏标题

    final labels = tester
        .widgetList<Text>(find.descendant(
          of: find.byType(NavigationDrawer),
          matching: find.byType(Text),
        ))
        .map((widget) => widget.data ?? '')
        .where((text) => text.isNotEmpty)
        .toList();

    expect(labels.length, greaterThan(20), reason: '抽屉里应渲染出全部模块项（实际 ${labels.length} 个）');

    for (final text in labels) {
      expect(
        RegExp(r'[一-鿿]').hasMatch(text),
        isFalse,
        reason: 'en 语言下不该出现汉字 ⇒ 「$text」是硬编码中文、没走 t()',
      );
      expect(
        RegExp(r'^[a-z]+\.[a-z_]+$').hasMatch(text),
        isFalse,
        reason: '界面露出了词条键「$text」⇒ 存了键但渲染处忘了 t()',
      );
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

  testWidgets('用户菜单: 语言菜单列出全部 13 种（母语名）', (tester) async {
    await pumpLayout(tester);

    await tester.tap(find.text('Admin'));
    await tester.pumpAndSettle();

    // 旧实现是一个「中/英互切」的开关，文案写死成「切换到中文」——
    // 后端早已支持 13 种，界面却只能二选一。现在由 LocaleController.supported 驱动平铺。
    // 断言母语名而不是译名：语言菜单的可用性前提就是「用户还看不懂当前界面语言时也能选对」。
    for (final item in LocaleController.supported) {
      expect(find.text(item.nativeName), findsOneWidget, reason: '语言菜单缺 ${item.code}');
    }
  });

  testWidgets('语言码同步到请求头（X-Language 的来源）', (tester) async {
    await pumpLayout(tester);

    // 旧实现写死 `['en','zh'].contains(saved)`：其余 11 种连存都存不进去。
    // 这里挑一个「不在旧白名单里」的语言，正是要钉住那个修复。
    Get.find<LocaleController>().changeLocale('ja');
    await tester.pumpAndSettle();

    expect(Get.find<LocaleController>().currentLocale.value, 'ja');
    expect(
      LocaleController.currentCode,
      'ja',
      reason: 'currentCode 是 ApiService 拼 X-Language 读的那个字段；两者不同步就会出现'
          '「界面切成日语、请求头还是旧语言」，服务端 message 跟着不对',
    );
  });

  testWidgets('用户菜单: 语言切换 zh → 界面文案变化', (tester) async {
    await pumpLayout(tester);

    await tester.tap(find.text('Admin'));
    await tester.pumpAndSettle();
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
