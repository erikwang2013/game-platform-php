// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 文件名刻意**不带 `_test` 后缀**：`bigscreen_page.dart` 顶层 `import 'dart:js_interop'`
// 在 Dart VM 上不可用（`flutter test` 默认平台），本文件一旦被默认套件扫到就会与
// `widget_test.dart` 一样「加载即失败」。它只能在 web 平台跑：
//
//     CHROME_EXECUTABLE=/usr/bin/google-chrome \
//       flutter test --platform chrome test/bigscreen_page_web.dart
//
// 这条用例是 `bigscreen_page.dart` **唯一的观察路径**（VM 上该文件根本编不过，
// 故 `flutter test` / `flutter analyze` 都看不见它的运行时行为）。
//
// 钉的是「平台总览」面板那条**曾经不存在**的取数：
//   - 请求必须是 `GET /admin/v1/dashboard`（路由表里没有 `/dashboard/stats`，
//     打它就是 404 → 被 `_load()` 的 catch 静默吞掉 → 面板永远空白且不报错）；
//   - 渲染的必须是该响应 `data.stats` 里的条目（label + value 两个键）。
// 把断言的路径改回 `/dashboard/stats`，本用例必须变红。
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:admin_app/app/i18n/locale_controller.dart';
import 'package:admin_app/app/pages/bigscreen/bigscreen_page.dart';
import 'package:admin_app/app/services/api_service.dart';

/// 假传输层：记录请求路径并按路径回罐头。走 `dio.httpClientAdapter` 替换而非起 HTTP ——
/// 测试绑定会把真实网络请求一律挡成 400，路径也观察不到。
class _CannedAdapter implements HttpClientAdapter {
  final List<String> paths = [];

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    paths.add(options.path);
    // `data` 的形状三个端点各不相同，且 `_load()` 里前一个抛异常会吃掉后一个请求：
    // report/daily 必须是**数组**（`List.from(Map)` 会抛），report/summary 要有 compare 键。
    final Object data;
    if (options.path.endsWith('/dashboard')) {
      // DashboardController::index 的 data.stats 形状：{label, value, icon, color, trend}
      data = {
        'stats': [
          {'label': 'Total users', 'value': '42'},
          {'label': 'New today', 'value': '7'},
        ]
      };
    } else if (options.path.endsWith('/report/daily')) {
      data = <Object>[];
    } else {
      data = <String, dynamic>{'compare': <String, dynamic>{}};
    }
    return ResponseBody.fromString(jsonEncode({'code': 0, 'message': 'success', 'data': data}), 200,
        headers: _jsonHeaders);
  }

  static const _jsonHeaders = {
    Headers.contentTypeHeader: [Headers.jsonContentType]
  };
}

void main() {
  testWidgets('大屏「平台总览」面板：取数打 /dashboard 并渲染 data.stats', (tester) async {
    SharedPreferences.setMockInitialValues({});
    Get.reset();
    Get.put(LocaleController());

    final adapter = _CannedAdapter();
    ApiService().dio.httpClientAdapter = adapter;

    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(const GetMaterialApp(home: BigscreenPage()));

    // 4 屏轮播每 15 秒切一次，第 4 屏（index 3）才是平台总览。
    // 每轮先 runAsync 让**真实**异步链推进（web 上 SharedPreferences / dio 走的不是
    // FakeAsync 控得住的那套微任务，光 pump 假时钟会停在第一个请求之后、永远等不到第二三个），
    // 再用 pump 推假时钟触发轮播。不用 pumpAndSettle：页内 spinner 持续排帧，settle 会超时。
    for (var i = 0; i < 60; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      await tester.pump(const Duration(seconds: 1));
      if (find.text('Total users').evaluate().isNotEmpty) break;
    }

    // 诊断一律进 reason —— flutter test 的 web 报告器会把「抛出的异常日志」吞掉
    // （只留一句 See exception logs above），expect 的 Expected/Actual/reason 才印得出来。
    final rendered =
        find.byType(Text).evaluate().map((e) => (e.widget as Text).data).toList().toString();
    expect(adapter.paths.contains('/admin/v1/dashboard'), isTrue,
        reason: '平台面板必须打 /admin/v1/dashboard；实测路径=${adapter.paths}');
    expect(adapter.paths.any((p) => p.contains('/dashboard/stats')), isFalse,
        reason: '路由表里没有 /dashboard/stats，打它就是静默 404；实测路径=${adapter.paths}');
    expect(find.text('Platform Overview'), findsOneWidget, reason: '实测渲染=$rendered');
    expect(find.text('Total users'), findsOneWidget, reason: '实测渲染=$rendered');
    expect(find.text('42'), findsOneWidget, reason: '实测渲染=$rendered');
    expect(find.text('New today'), findsOneWidget, reason: '实测渲染=$rendered');
    expect(find.text('7'), findsOneWidget, reason: '实测渲染=$rendered');
  });
}
