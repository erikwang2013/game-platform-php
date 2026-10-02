// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;
import 'package:game_platform/app/i18n/translations.dart';
import 'package:game_platform/app/pages/friend/friend_page.dart';
import 'package:game_platform/app/services/api_service.dart';

import 'fake_http_adapter.dart';

/// 「接受/拒绝/删除」三个 mutation 曾是 `} catch (_) {}`：失败与成功在界面上长得一模一样
/// （同屏的「加好友」`_sendRequest` 却给 snackbar ⇒ 不是风格选择）。
/// 这组钉子钉住：失败必须给用户看得见的提示。
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    // 请求拦截器要读 token ⇒ 不 mock 掉安全存储会挂在 MissingPluginException 上
    FlutterSecureStorage.setMockInitialValues({});
  });

  setUp(() {
    Get.reset();
    final adapter = FakeAdapter((o) {
      final path = o.uri.path;
      if (path.endsWith('/friend/list')) {
        return Future.value(jsonOk([
          {'id': 'f1', 'username': 'carol'},
        ]));
      }
      if (path.endsWith('/friend/requests')) {
        return Future.value(jsonOk([
          {
            'id': 'r1',
            'user': {'username': 'bob'},
          },
        ]));
      }
      if (path.endsWith('/friend/accept')) return Future.value(jsonFail(422, 'Already friends'));
      if (path.endsWith('/friend/reject')) return Future.value(jsonFail(500, 'Reject failed'));
      if (path.endsWith('/friend/remove')) {
        // 连不上（网络层失败）：走 catch (_) 那支
        return Future.error(DioException(requestOptions: o, message: 'connection refused'));
      }
      return Future.value(jsonOk(const []));
    });
    ApiService().dio.httpClientAdapter = adapter;
  });

  /// 放掉 snackbar 的自动关闭定时器，别给下一条用例留 pending timer
  Future<void> drainSnackbar(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 4));
    await tester.pumpAndSettle();
  }

  testWidgets('接受失败：服务端 message 透给用户（不再静默）', (tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: FriendPage()));
    await tester.pumpAndSettle();
    await tester.tap(find.text('${AppTranslations.t('friend.tab_requests')}'));
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.check), findsOneWidget); // 请求列表确实渲染出来了

    await tester.tap(find.byIcon(Icons.check));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Already friends'), findsOneWidget);
    await drainSnackbar(tester);
  });

  testWidgets('拒绝失败：同样给提示（与接受同一套待遇）', (tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: FriendPage()));
    await tester.pumpAndSettle();
    await tester.tap(find.text('${AppTranslations.t('friend.tab_requests')}'));
    await tester.pumpAndSettle();

    await tester.tap(find.byIcon(Icons.close));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Reject failed'), findsOneWidget);
    await drainSnackbar(tester);
  });

  testWidgets('删除好友网络失败：走通用错误文案（覆盖 catch (_) 那支）', (tester) async {
    await tester.pumpWidget(const GetMaterialApp(home: FriendPage()));
    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.person_remove), findsOneWidget); // 好友列表有数据

    await tester.tap(find.byIcon(Icons.person_remove));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('${AppTranslations.t('app.network_error')}'), findsOneWidget);
    await drainSnackbar(tester);
  });
}
