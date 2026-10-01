// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 编辑态改管理员密码的**两个字段名**（UserController::update → BaseController::confirmPassword）：
//   - `password`       = 新密码
//   - `admin_password` = **当前操作者自己的**登录密码（二次确认）
// 只发前者必被 422 挡下，服务端只说「This sensitive operation requires password confirmation」，
// 不告诉你是缺了哪个字段 —— 静默失败，所以钉在这里。
// 两个键**对调**同样是错的（那等于拿被改账号的新密码去验操作者身份），故断言逐键比对。
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

import 'package:admin_app/app/pages/user/user_form_page.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'test_helpers.dart';

/// 假传输层：记录请求并回成功信封。测试绑定会把真实网络一律挡成 400，
/// 请求体也观察不到。
class _RecordingAdapter implements HttpClientAdapter {
  final List<String> paths = [];
  final List<Object?> bodies = [];

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    paths.add(options.path);
    final raw = options.data;
    bodies.add(raw is String ? jsonDecode(raw) : raw);
    return ResponseBody.fromString(
        jsonEncode({'code': 0, 'message': 'success', 'data': <String, dynamic>{}}), 200,
        headers: const {Headers.contentTypeHeader: [Headers.jsonContentType]});
  }
}

void main() {
  setUp(setUpTest);

  final existing = <String, dynamic>{
    'id': 'abc123',
    'username': 'root',
    'real_name': 'Root',
    'phone': '',
    'email': '',
    'status': 1,
  };

  /// 打开编辑态表单（经 Get.to 推入，页面里的 Get.back() 才有栈可退）
  Future<_RecordingAdapter> openEditForm(WidgetTester tester) async {
    final adapter = _RecordingAdapter();
    ApiService().dio.httpClientAdapter = adapter;

    tester.view.physicalSize = const Size(1400, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(GetMaterialApp(
      locale: const Locale('en', 'US'),
      home: Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => Get.to(() => UserFormPage(userData: existing)),
          child: const Text('open'),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    return adapter;
  }

  /// 密码字段在表单里的位置：username(0) / password(1) / real_name(2) / phone(3) / email(4)
  Finder passwordField() => find.byType(TextFormField).at(1);

  Finder inDialog(Finder matching) =>
      find.descendant(of: find.byType(AlertDialog), matching: matching);

  testWidgets('编辑态改密码：先弹二次确认，body 同时带 password 与 admin_password（不换位）',
      (tester) async {
    final adapter = await openEditForm(tester);

    await tester.enterText(passwordField(), 'NewPass123');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    // 二次确认框：收的是操作者自己的密码，不是刚输入的新密码
    expect(find.byType(AlertDialog), findsOneWidget);
    expect(adapter.paths, isEmpty, reason: '确认之前不许发请求');

    await tester.enterText(inDialog(find.byType(TextField)), 'MyOwn456');
    await tester.tap(inDialog(find.widgetWithText(ElevatedButton, 'Confirm')));
    await tester.pumpAndSettle();

    expect(adapter.paths, hasLength(1));
    expect(adapter.paths.single, '/admin/v1/user/abc123');
    expect(adapter.bodies.single, {
      'real_name': 'Root',
      'status': 1,
      'phone': '',
      'email': '',
      'password': 'NewPass123',
      'admin_password': 'MyOwn456',
    });
  });

  testWidgets('编辑态不改密码：不弹确认框，请求里两个键都不出现', (tester) async {
    final adapter = await openEditForm(tester);

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.byType(AlertDialog), findsNothing);
    expect(adapter.bodies.single, {
      'real_name': 'Root',
      'status': 1,
      'phone': '',
      'email': '',
    });
  });

  testWidgets('二次确认框取消：不发请求，页面不关', (tester) async {
    final adapter = await openEditForm(tester);

    await tester.enterText(passwordField(), 'NewPass123');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    await tester.tap(inDialog(find.widgetWithText(TextButton, 'Cancel')));
    await tester.pumpAndSettle();

    expect(adapter.paths, isEmpty);
    expect(find.byType(UserFormPage), findsOneWidget);
  });
}
