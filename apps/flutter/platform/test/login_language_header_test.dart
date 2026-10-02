// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/i18n/locale_controller.dart';
import 'package:game_platform/app/pages/login/login_page.dart';
import 'package:game_platform/app/services/auth_service.dart';

import 'fake_http_adapter.dart';

/// 登录页自带裸 Dio（**刻意绕开 ApiService 的 401-refresh**：登录失败本身就是 401，
/// 挂上去会形成刷新回环），代价是 X-Language 也一起丢了 ⇒ 语言切到 en/ja 后
/// 登录/注册的服务端 message 仍永远是中文。这组钉子钉住语言头，同时钉住「不许把
/// refresh 拦截器一起接上来」。
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    FlutterSecureStorage.setMockInitialValues({'access_token': 'tok-1', 'refresh_token': 'r-1'});
  });

  test('登录/注册请求带 X-Language：现读当前语言，切了语言也生效', () {
    addTearDown(() => LocaleController.currentCode = 'en');

    LocaleController.currentCode = 'ja';
    final dio = buildLoginDio();
    final options = RequestOptions(path: '/api/v1/auth/login');
    for (final interceptor in dio.interceptors) {
      interceptor.onRequest(options, RequestInterceptorHandler());
    }
    expect(options.headers['X-Language'], 'ja');

    LocaleController.currentCode = 'de';
    final options2 = RequestOptions(path: '/api/v1/auth/register');
    for (final interceptor in dio.interceptors) {
      interceptor.onRequest(options2, RequestInterceptorHandler());
    }
    expect(options2.headers['X-Language'], 'de');
  });

  test('登录 401 不会被接力成刷新/登出：已有会话的 token 原样保留（防刷新回环）', () async {
    final dio = buildLoginDio();
    final adapter = FakeAdapter((o) => Future.value(ResponseBody.fromString(
          jsonEncode({'code': 401, 'message': 'invalid credentials'}),
          401,
          headers: {
            Headers.contentTypeHeader: [Headers.jsonContentType],
          },
        )));
    dio.httpClientAdapter = adapter;

    // 登录前已登录过（或有旧会话）：若 ApiService 那套拦截器被接上来，
    // 这个 401 会触发 tryRefresh → clearToken → 跳到 /login 的回环
    expect(await AuthService.getToken(), 'tok-1');

    await expectLater(
      dio.post('/api/v1/auth/login', data: {'username': 'a', 'password': 'b'}),
      throwsA(isA<DioException>()),
    );

    expect(adapter.requests.length, 1); // 没有悄悄重发第二次
    expect(await AuthService.getToken(), 'tok-1'); // token 没被顺带清掉
    expect(dio.interceptors.whereType<InterceptorsWrapper>().length, 1); // 只有语言那一支
  });
}
