// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;
import 'package:game_platform/app/i18n/translations.dart';
import 'package:game_platform/app/pages/chat/chat_page.dart';
import 'package:game_platform/app/pages/game/game_detail_page.dart';
import 'package:game_platform/app/services/api_service.dart';
import 'package:game_platform/app/services/chat_service.dart';

import 'fake_http_adapter.dart';

/// 深链/刷新：`Get.arguments` 只在当次导航的内存里活着，F5 后为 null。
/// 旧代码在 initState 里 `Get.arguments as Map<String, dynamic>` 非空转换 ⇒ TypeError ⇒
/// 白屏（release 灰屏）且无出口。这组钉子钉住「自救 + 有出口的错误态」。
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    // 请求拦截器要读 token ⇒ 不 mock 掉安全存储会挂在 MissingPluginException 上
    FlutterSecureStorage.setMockInitialValues({});
  });

  setUp(() {
    Get.reset();
    Get.parameters = {};
    // 取数一律失败（断网等价）：自救的那次请求必然走失败分支
    ApiService().dio.httpClientAdapter = failingAdapter();
  });

  test('deepLinkParam（从 URL 自救的取参）：Get.parameters 优先，空串按没给处理', () {
    Get.parameters = {'id': 'g1'};
    expect(deepLinkParam('id'), 'g1');

    Get.parameters = {'id': ''};
    expect(deepLinkParam('id'), isNull);

    Get.parameters = {};
    expect(deepLinkParam('id'), isNull);
  });

  group('/game-detail', () {
    testWidgets('常规导航（有 arguments）：照旧直接渲染，且带 parameters 的新路由名仍能匹配', (tester) async {
      await tester.pumpWidget(GetMaterialApp(
        getPages: [
          GetPage(name: '/', page: () => const SizedBox.shrink()),
          GetPage(name: '/game-detail', page: () => const GameDetailPage()),
        ],
        initialRoute: '/',
      ));
      await tester.pumpAndSettle();

      // 与 game_hall_page 同形：parameters 让 id 进 URL（F5 自救全靠它）
      Get.toNamed('/game-detail', parameters: {'id': 'g9'}, arguments: {'id': 'g9', 'name': 'Chess'});
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Chess'), findsWidgets);
      expect(find.text('${AppTranslations.t('game_detail.back_to_hall')}'), findsOneWidget);
    });

    testWidgets('无 arguments 又无 URL 参数：有出口的错误态，不白屏', (tester) async {
      await tester.pumpWidget(const GetMaterialApp(home: GameDetailPage()));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull); // 旧代码在这里抛 TypeError
      expect(find.text('${AppTranslations.t('game_detail.back_to_hall')}'), findsOneWidget); // 出口
      expect(find.text('${AppTranslations.t('app.retry')}'), findsNothing); // 没有 hashid 无从重试
    });

    testWidgets('无 arguments 但有 URL 的 id：按 hashid 自救，拉不到也给可重试的错误态', (tester) async {
      Get.parameters = {'id': 'g1'};
      await tester.pumpWidget(const GetMaterialApp(home: GameDetailPage()));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      // 走到了详情取数分支（这里取数失败）⇒ 错误态 + 重试
      expect(find.text('${AppTranslations.t('app.retry')}'), findsOneWidget);
      expect(find.text('${AppTranslations.t('game_detail.back_to_hall')}'), findsOneWidget);
    });
  });

  group('/chat', () {
    testWidgets('无 arguments 又无 URL 参数：有出口的错误态，不白屏', (tester) async {
      Get.put(ChatService());
      await tester.pumpWidget(const GetMaterialApp(home: ChatPage()));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull); // 旧代码在这里抛 TypeError
      expect(find.text('${AppTranslations.t('chat.title')}'), findsWidgets); // 消息列表出口
      expect(find.text('${AppTranslations.t('game_detail.back_to_hall')}'), findsOneWidget); // 大厅出口
    });

    testWidgets('无 arguments 但有 URL 的 peer_id：进会话（消息拉取失败也不白屏）', (tester) async {
      Get.put(ChatService());
      Get.parameters = {'peer_id': 'kR3nQ8'};
      await tester.pumpWidget(const GetMaterialApp(home: ChatPage()));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.byType(TextField), findsOneWidget); // 是会话页而不是错误态
      expect(find.text('${AppTranslations.t('game_detail.back_to_hall')}'), findsNothing);
    });
  });

  /// 从 `toNamed(` 的左括号起做括号配平，取出整个调用表达式。
  String callText(String src, int openParen) {
    var depth = 0;
    for (var i = openParen; i < src.length; i++) {
      if (src[i] == '(') depth++;
      if (src[i] == ')') {
        depth--;
        if (depth == 0) return src.substring(openParen, i + 1);
      }
    }
    fail('括号不配平，扫描器本身坏了: ${src.substring(openParen, openParen + 60)}');
  }

  test('所有 /chat 与 /game-detail 的导航点都必须带 parameters（URL 自救的唯一来源）', () {
    // 缺陷 #6 的形状是「导航点漏传 parameters ⇒ F5 后无从自救」。只修页面不修调用点，
    // 或者将来新增第四个调用点时又漏传，都会在这里红 —— 这是唯一能覆盖全部调用点的判据。
    const routes = ["toNamed('/chat'", "toNamed('/game-detail'"];
    final found = {for (final r in routes) r: 0};
    final offenders = <String>[];

    for (final entity in Directory('lib').listSync(recursive: true).whereType<File>()) {
      if (!entity.path.endsWith('.dart')) continue;
      final src = entity.readAsStringSync();
      for (final route in routes) {
        var idx = src.indexOf(route);
        while (idx != -1) {
          // route 以 `toNamed('` 开头 ⇒ 它的实参表就是紧随其后的那个 `(`。
          // （别回头找左括号：`onTap: () => Get.toNamed(...)` 会撞上 lambda 的 `()`）
          final p = idx + 'toNamed'.length;
          expect(src[p], '(', reason: '扫描器坏了：${src.substring(idx, idx + 20)}');
          final call = callText(src, p);
          found[route] = found[route]! + 1;
          if (!call.contains('parameters:')) offenders.add('${entity.path}: $call');
          idx = src.indexOf(route, idx + 1);
        }
      }
    }

    // 防恒真：扫不到调用点（改写成变量路由等）时这段必须红，而不是静默通过
    expect(found["toNamed('/chat'"], greaterThanOrEqualTo(2)); // 消息列表、好友页
    // 大厅原本两处调用点（卡片点击 / 「进入游戏」按钮）在 2026-10-02 那次拆分里合并成了
    // 同一个 `_open()` ⇒ 字面量只剩一处。阈值跟着降到 1：仍然要求「扫得到」，只是不再要求
    // 「扫到两条」——两条调用点在源码上已经不存在，留 2 会让这条守卫永久假红。
    expect(found["toNamed('/game-detail'"], greaterThanOrEqualTo(1)); // 大厅（1 个调用点）
    expect(offenders, isEmpty, reason: '这些导航点在 F5/深链后无法自救：\n${offenders.join('\n')}');
  });
}
