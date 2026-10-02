// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;
import 'package:game_platform/app/i18n/translations.dart';
import 'package:game_platform/app/pages/leaderboard/leaderboard_page.dart';
import 'package:game_platform/app/services/api_service.dart';

import 'fake_http_adapter.dart';

/// 旧代码 `_loadRanking` 的 `} catch (_) { _ranking = []; }` 把取数失败静默折成空列表，
/// 页面于是把「拉取失败」渲染成「暂无排名」——用户看到的是数据结论，实际是请求失败。
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    // 请求拦截器要读 token ⇒ 不 mock 掉安全存储会挂在 MissingPluginException 上
    FlutterSecureStorage.setMockInitialValues({});
  });

  test('maskUserId：hashid 取末 4 个字符；短串不越界；空值回 -', () {
    expect(maskUserId('aB3xK9Qz'), '#···K9Qz');
    expect(maskUserId('ab'), '#···ab');
    expect(maskUserId(null), '-');
  });

  test('四态判定：失败必须与「真没人上榜」分开', () {
    expect(rankingViewOf(loading: false, error: 'boom', count: 0), RankingView.failed);
    expect(rankingViewOf(loading: false, error: null, count: 0), RankingView.empty);
    expect(rankingViewOf(loading: false, error: null, count: 3), RankingView.data);
    expect(rankingViewOf(loading: true, error: null, count: 0), RankingView.loading);
  });

  testWidgets('榜单拉取失败：错误态 + 可重试；重试成功后出数据（不是「暂无排名」）', (tester) async {
    Get.reset();
    var attempt = 0;
    final adapter = FakeAdapter((o) {
      if (o.uri.path.endsWith('/leaderboard/list')) {
        return Future.value(jsonOk({
          'items': [
            {'id': 'b1', 'name': 'Weekly'},
          ],
        }));
      }
      attempt++;
      if (attempt == 1) return Future.value(jsonFail(500, 'boom'));
      return Future.value(jsonOk({
        'ranking': [
          // 真契约形状：只有 rank / user_id / score —— `username` 后端从不下发，
          // 旧用例喂的 'alice' 是条生产上不可能走到的路径。
          {'rank': 1, 'user_id': 'aB3xK9Qz', 'score': 10},
        ],
      }));
    });
    ApiService().dio.httpClientAdapter = adapter;

    await tester.pumpWidget(const GetMaterialApp(home: LeaderboardPage()));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('boom'), findsOneWidget); // 失败原因透出
    expect(find.text('${AppTranslations.t('leaderboard.empty')}'), findsNothing); // 不是「暂无排名」

    await tester.tap(find.text('${AppTranslations.t('app.retry')}'));
    await tester.pumpAndSettle();

    expect(find.text('#···K9Qz'), findsOneWidget); // 重试真的重新取数（身份列只露末 4 个字符）
    expect(find.text('boom'), findsNothing);
  });
}
