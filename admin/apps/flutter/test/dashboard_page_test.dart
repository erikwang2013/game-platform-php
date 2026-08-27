// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/dashboard/dashboard_page.dart';
import 'test_helpers.dart';

void main() {
  setUp(setUpTest);

  Future<void> pumpDashboard(WidgetTester tester) async {
    tolerateKnownDashboardOverflow(); // 须在 testWidgets 体内调用(binding 已安装自身 onError)
    // PC 管理后台目标尺寸，避免 800x600 默认视口下统计卡溢出
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(const GetMaterialApp(home: DashboardPage()));
    // 网络被 flutter_test 隔离（400）→ DashboardController 捕获异常回退到模拟数据
    await tester.pumpAndSettle();
  }

  testWidgets('仪表盘离线渲染: 标题 + 4 张模拟统计卡', (tester) async {
    await pumpDashboard(tester);

    expect(find.text('Dashboard'), findsOneWidget);
    // 控制器 catch 分支写入的模拟数据
    expect(find.text('1,236'), findsOneWidget);
    expect(find.text('89'), findsOneWidget);
    expect(find.text('28'), findsOneWidget);
    expect(find.text('452'), findsOneWidget);
    expect(find.text('Data Trend (30 days)'), findsOneWidget);
    expect(find.text('User Status Distribution'), findsOneWidget);
  });

  testWidgets('仪表盘导出菜单: PDF / Excel 两项', (tester) async {
    await pumpDashboard(tester);

    await tester.tap(find.byIcon(Icons.download));
    await tester.pumpAndSettle();

    expect(find.text('Export PDF'), findsOneWidget);
    expect(find.text('Export Excel'), findsOneWidget);
  });
}
