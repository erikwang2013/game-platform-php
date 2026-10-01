// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/login/login_page.dart';
import 'test_helpers.dart';

/// 一发罐头 `/api/v1/captcha/generate` 响应里的**真 300×200 PNG**（服务端画布原生尺寸）。
/// 内联而不是读盘：用例要能在任何机器上跑，不能依赖 /tmp 里有没有那张图。
const _captchaPngB64 =
    'iVBORw0KGgoAAAANSUhEUgAAASwAAADICAIAAADdvUsCAAAC8UlEQVR42u3cMU7DQBCGURxFisQZqHMVTpUqp+IqqTlACityOloaJCxhvDP/ezUFXubzLEFius/LC7CfgyMAEYIIARGCCAERgggBEYIIARGCCAERgggBEYIIARGCCAERgggBEYIIARGCCAERgggBEYIIARGCCAERgggBEYIIARGCCAERgggBEYIIgfWOsU9+vp5+/8W3y9OssJHpPi+qW0uTiHCf9tSICEdpT42IcKD8pIgIh8hPiqRHOEh+UiQxwgHzkyKrHBQY/h1iE6YMt5VIq01Ycb1YifSJsO4065Dy19E2Q+xqSslN2GmNWInUi7Df1OqQShF2nVcdUiPC3pOqQ0aPMGFGdYh/bwEijF8RlmG4Qf9OGDiXA/7x8PXjLe2n8Hj/tAlzN4N96DoKiDB+IViGIjSFTgCbEERoCTgHbEIQode/08AmBBF68TsTbEIQoVe+k8EmBBECvSN043I+2IQgQhChu5YbKTYhiBAQIYgQSIrQ5w3OCpsQRAiIEEQIIgRECCIERAgiBEQIIgRECCIERAgi/Ae3y9PpOytsQhAhiNARgAhBhDvxeYNTwiYEEYII3bXcRbEJQYRAcoRuXE5GhEB8hF75zkSEQHyEXvxOQ4RAfIRe/85BhEB8hJaAExChKfTsuI6CCC0ET41NGDeRChShufSkiBBEaEV4RkQYN6MKpMZ1tOukKpBKvxP2m1cFUizCZlOrQL47lpvd8/UkP2xCc6xAgiMsOs0KpMN1tOjVVH403ISF5luBtN2E469E+ZES4YApyo/ECAdJUX6kR7hjivJDhD9WsWmN2kOE+9SoPUT4B+WsalJ1iNBNkrb8ewsQIYgQECGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEBAhiBDY0HSfF6cANiGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEBAhiBAQIYgQECGIEFjpC+gZ8KuxWnd5AAAAAElFTkSuQmCC';

/// 把 dio 底下的 `dart:io` HttpClient 整发顶掉，回一发罐头。
///
/// 为什么非这样不可：`LoginPage` 的 `_captcha` 是**字段初始化器**里 new 出来的
/// （`CaptchaService(Dio(...))`），构造函数不接注入 ⇒ 没有从外面塞桩的口子。
/// 而登录页的验证码弹框只有 `_captchaData != null` 才会开，测试环境没网就永远开不了框
/// —— 「弹框宽 300」这条断言此前**无法写**，这正是它裸奔到现在的原因。
/// （flutter_test 默认的 HttpOverrides 对一切请求回 400，这里整个 replace 掉。）
class _CannedCaptchaOverrides extends HttpOverrides {
  _CannedCaptchaOverrides(this.body);
  final String body;

  @override
  HttpClient createHttpClient(SecurityContext? context) => _FakeHttpClient(body);
}

class _FakeHttpClient implements HttpClient {
  _FakeHttpClient(this.body);
  final String body;

  @override
  Future<HttpClientRequest> openUrl(String method, Uri url) async => _FakeRequest(body);

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _FakeRequest implements HttpClientRequest {
  _FakeRequest(this.body);
  final String body;

  @override
  final HttpHeaders headers = _FakeHeaders();

  /// dio 的 io_adapter 把 `addStream(...)` 的返回值直接赋给一个 Future 类型的局部变量
  /// ⇒ **必须真返回 Future**；靠 `noSuchMethod` 退成 null 会炸成
  /// `type 'Null' is not a subtype of type 'Future'`，而 `_loadCaptcha` 的宽 catch
  /// 会把它吞成「取码失败」—— 看着像没网，其实是桩写漏了。
  ///
  /// 同族陷阱：响应头不报 `content-type: application/json` 的话 dio 不按 JSON 解码，
  /// `resp.data` 会是 String，症状与上面一模一样。两个都踩过。
  @override
  Future<void> addStream(Stream<List<int>> stream) async {}

  @override
  Future<HttpClientResponse> close() async => _FakeResponse(utf8.encode(body));

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _FakeResponse extends Stream<List<int>> implements HttpClientResponse {
  _FakeResponse(this.bytes);
  final List<int> bytes;

  @override
  int get statusCode => 200;

  @override
  String get reasonPhrase => 'OK';

  @override
  int get contentLength => bytes.length;

  @override
  HttpHeaders get headers => _FakeHeaders('application/json; charset=utf-8');

  @override
  bool get isRedirect => false;

  @override
  List<RedirectInfo> get redirects => const [];

  @override
  bool get persistentConnection => false;

  @override
  HttpClientResponseCompressionState get compressionState =>
      HttpClientResponseCompressionState.notCompressed;

  @override
  StreamSubscription<List<int>> listen(
    void Function(List<int> event)? onData, {
    Function? onError,
    void Function()? onDone,
    bool? cancelOnError,
  }) =>
      Stream<List<int>>.value(bytes).listen(
        onData,
        onError: onError,
        onDone: onDone,
        cancelOnError: cancelOnError,
      );

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

/// 请求头是个无底洞：dio 只往里写，读回来的东西这里没有语义。
/// 响应侧必须**报出 `content-type: application/json`** —— 缺了它 dio 的默认
/// transformer 不按 JSON 解码，`resp.data` 会是 String，`resp.data['code']` 直接抛，
/// 表现是「取码失败」而**不是**「弹框尺寸不对」，很容易误诊。
class _FakeHeaders implements HttpHeaders {
  _FakeHeaders([this._contentType]);

  final String? _contentType;

  @override
  void set(String name, Object value, {bool preserveHeaderCase = false}) {}

  @override
  void add(String name, Object value, {bool preserveHeaderCase = false}) {}

  @override
  void forEach(void Function(String name, List<String> values) action) {
    if (_contentType != null) action('content-type', [_contentType]);
  }

  @override
  List<String>? operator [](String name) =>
      name.toLowerCase() == 'content-type' && _contentType != null ? [_contentType] : null;

  @override
  String? value(String name) => this[name]?.first;

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

/// 罐头响应体：`CaptchaService.generate()` 的形状（`key` / `image` / `extra.texts`）。
String _cannedBody() => jsonEncode({
      'code': 0,
      'message': 'ok',
      'data': {
        'key': 'k1',
        'image': _captchaPngB64,
        'extra': {
          'texts': [
            {'order': 1, 'text': '骰'},
          ],
        },
      },
    });

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

  // ---------- 验证码弹框尺寸 ----------
  //
  // 钉的是「弹框内容盒 = 服务端画布原生宽 300」这条：把 `_captchaDialog` 里的
  // `width: 300` 改回 400，本组必须变红（图会被放大到 400×266.7）。
  group('验证码弹框尺寸（画布原生 300×200）', () {
    /// 开框：罐头取码 → 填账号密码 → 点登录 → 弹框出现。
    ///
    /// **取码段必须包在 `runAsync` 里**：`_loadCaptcha` 链上有 `ui.instantiateImageCodec`
    /// —— 那是真引擎调用，等的是**真事件循环**，`tester.pump(Duration)` 推的是假时钟，
    /// 推多少帧都不会让它完成。结果就是 `_captchaData` 永远为 null、`_login()` 只把
    /// `_error` 设成 `loadCaptcha`，弹框根本不开（表现是「找不到 安全验证」，
    /// 很容易误诊成「文案改了」或「按钮点不着」）。
    Future<void> openDialog(WidgetTester tester) async {
      await tester.runAsync(() async {
        await tester.pumpWidget(const GetMaterialApp(home: LoginPage()));
        for (var i = 0; i < 10; i++) {
          await Future<void>.delayed(const Duration(milliseconds: 20));
          await tester.pump();
        }
      });
      await tester.enterText(find.byType(TextField).at(0), 'admin');
      await tester.enterText(find.byType(TextField).at(1), 'secret');
      await tester.tap(find.byType(FilledButton));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
    }

    testWidgets('画布按 300×200 原生尺寸渲染（不是 400×266.7）', (tester) async {
      HttpOverrides.global = _CannedCaptchaOverrides(_cannedBody());
      addTearDown(() => HttpOverrides.global = null);

      await openDialog(tester);

      expect(find.text('安全验证'), findsOneWidget, reason: '验证码弹框没开起来');
      // 登录页本来就有品牌图 ⇒ 必须收进弹框里找，否则 finder 不唯一（选中的可能是 logo）
      final img = find.descendant(of: find.byType(AlertDialog), matching: find.byType(Image));
      expect(img, findsOneWidget);
      // Image 的 width 取 LayoutBuilder 的 maxWidth，也就等于 SizedBox 的 300
      expect(
        tester.getSize(img),
        const Size(300, 200),
        reason: '图被放大了 —— 弹框内容盒不是画布原生宽 300',
      );
    });

    testWidgets('点画布 30%/40% 处 → 交给服务端的是画布坐标 (90, 80)，与显示尺寸无关',
        (tester) async {
      HttpOverrides.global = _CannedCaptchaOverrides(_cannedBody());
      addTearDown(() => HttpOverrides.global = null);

      await openDialog(tester);

      final img = find.descendant(of: find.byType(AlertDialog), matching: find.byType(Image));
      expect(img, findsOneWidget);
      final box = tester.getRect(img);
      await tester.tapAt(Offset(
        box.left + box.width * 0.30,
        box.top + box.height * 0.40,
      ));
      await tester.pump();

      // 落点圆的圆心就是 `_onCaptchaTap` 算出来的 (x, y)：它按
      // `显示点击位置 / 显示尺寸 × _imgSize` 换算，而 `_imgSize` = 服务端画布原生 300×200。
      // ⇒ 把圆心的**屏幕偏移**按同一比例折回画布坐标，应恰是 30%/40% × 300/200 = (90, 80)。
      //
      // ⚠ 这里必须**折回去**，不能直接断言屏幕偏移等于 90/80：后者只在「显示宽 == 画布宽」
      // 时才成立，那就退化成第二条尺寸检查（把 width 改成 400 它会一起红，看着像坐标被带偏，
      // 其实是同一件事说了两遍）。折回画布坐标后本条与尺寸**解耦**：实测 width→400 时
      // 尺寸那条红、本条绿。
      //
      // 本条实际咬得住的是**轴序与偏移**（实测：把 dx/dy 两根轴对调 ⇒ 本条红、尺寸那条绿）。
      // **咬不住纯比例错误**：`_onCaptchaTap` 算出的 x 与标记点 `Positioned` 的定位用的是
      // 同一个 `img.width` ⇒ 把两处一起换成 `box.width` 误差自相抵消，本条仍是绿的。
      // 别把这条当「换算完全正确」的证。
      final center = tester.getCenter(find.byType(Positioned).first);
      final canvasX = (center.dx - box.left) / box.width * 300;
      final canvasY = (center.dy - box.top) / box.height * 200;
      expect(canvasX, closeTo(90, 1.0), reason: '交给服务端的 x 不是画布坐标');
      expect(canvasY, closeTo(80, 1.0), reason: '交给服务端的 y 不是画布坐标');
    });
  });
}
