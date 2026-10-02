// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;
import 'package:game_platform/app/i18n/translations.dart';
import 'package:game_platform/app/pages/chat/chat_page.dart';
import 'package:game_platform/app/services/api_service.dart';
import 'package:game_platform/app/services/chat_service.dart';

import 'fake_http_adapter.dart';

/// 旧代码 `_send()`：气泡先上屏、`sendMessage` 不 await 也不 catch。
/// 失败时用户以为发出去了，而那条本地消息没有服务端 id（`mergeFor` 按 id 去重认不出），
/// 离开页面即永久消失。这组钉子钉住：失败必须看得见、且能重发。
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    // 请求拦截器要读 token ⇒ 不 mock 掉安全存储会挂在 MissingPluginException 上
    FlutterSecureStorage.setMockInitialValues({});
  });

  setUp(() {
    Get.reset();
    // 发送一律失败（断网等价）——正是要测的分支
    ApiService().dio.httpClientAdapter = failingAdapter();
    Get.put(ChatService());
    Get.parameters = {'peer_id': 'kR3nQ8'};
  });

  testWidgets('发送失败：气泡留着并标出可重发（不静默丢、不假装已发出）', (tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: ChatPage()));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '在吗');
    await tester.tap(find.byIcon(Icons.send));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull); // 旧代码在这里漏出未处理异步异常
    expect(find.text('在吗'), findsOneWidget); // 内容没丢
    expect(find.byIcon(Icons.refresh), findsOneWidget); // 明确标出「可重发」
    expect(find.text('${AppTranslations.t('app.network_error')}'), findsOneWidget);
  });

  testWidgets('点重发：再次尝试发送（失败仍标可重发，不会变成假成功）', (tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: ChatPage()));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '在吗');
    await tester.tap(find.byIcon(Icons.send));
    await tester.pumpAndSettle();

    await tester.tap(find.byIcon(Icons.refresh));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    // 重发不产生第二条气泡（内容仍只出现一次）
    expect(find.text('在吗'), findsOneWidget);
    expect(find.byIcon(Icons.refresh), findsOneWidget); // 还是失败 ⇒ 仍可重发
  });
}
