// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/login/login_page.dart';
import 'test_helpers.dart';

void main() {
  setUp(setUpTest);

  Future<void> pumpLogin(WidgetTester tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: LoginPage()));
    // 验证码加载在测试环境必然失败（网络被隔离）→ 显示错误横幅；
    // 登录页 captcha 区域此时渲染无限动画 spinner，不能用 pumpAndSettle
    await pumpUntil(tester, find.text('captchaLoadFailed'));
  }

  testWidgets('登录页渲染: 标题/用户名/密码/登录按钮', (tester) async {
    await pumpLogin(tester);

    expect(find.text('Game Platform Admin'), findsOneWidget);
    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Login'), findsOneWidget);
    // 密码框应脱敏
    final passwordField = tester.widget<TextField>(find.byType(TextField).at(1));
    expect(passwordField.obscureText, isTrue);
  });

  testWidgets('空表单提交 → 校验错误 enterCredentials', (tester) async {
    await pumpLogin(tester);

    await tester.tap(find.byType(FilledButton));
    await tester.pump();

    expect(find.text('enterCredentials'), findsOneWidget);
    expect(find.byIcon(Icons.error_outline), findsOneWidget);
  });

  testWidgets('填用户名密码但验证码未加载 → loadCaptcha 错误', (tester) async {
    await pumpLogin(tester);

    await tester.enterText(find.byType(TextField).at(0), 'admin');
    await tester.enterText(find.byType(TextField).at(1), 'secret');
    await tester.tap(find.byType(FilledButton));
    await tester.pump();

    expect(find.text('loadCaptcha'), findsOneWidget);
  });

  testWidgets('离线安全: 验证码加载失败被捕获而非崩溃', (tester) async {
    await pumpLogin(tester);
    expect(tester.takeException(), isNull);
  });
}
