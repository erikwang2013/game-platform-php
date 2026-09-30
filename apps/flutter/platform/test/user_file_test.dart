// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/pages/profile/identity_page.dart';
import 'package:game_platform/app/pages/profile/profile_page.dart';
import 'package:game_platform/app/routes/app_pages.dart';
import 'package:game_platform/app/services/api_service.dart';
import 'package:game_platform/app/services/user_file.dart';
import 'package:game_platform/app/widgets/user_file_image.dart';
// FormData / MultipartFile 与 dio 同名，测试里断言的是 dio 的那个
import 'package:get/get.dart' hide FormData, MultipartFile;

/// 1x1 透明 PNG：Image.memory 只认真图，占位字节会让 widget 走 errorBuilder
final Uint8List kPng = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
);

ResponseBody _json(Object body, {int status = 200}) => ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

ResponseBody _png(List<int> bytes) => ResponseBody.fromBytes(
      bytes,
      200,
      headers: {
        Headers.contentTypeHeader: ['image/png'],
      },
    );

class _FakeAdapter implements HttpClientAdapter {
  _FakeAdapter(this.handler);

  final Future<ResponseBody> Function(RequestOptions options) handler;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

/// 插件的 preprocess 正常应答（字段名与 service/vendor/…/UploadController::preprocess 一致）
Map<String, dynamic> _preprocessOk({String savedPath = ''}) => {
      'error': 0,
      'chunkSize': 1048576,
      'groupSubDir': 'aB12',
      'resourceTempBaseName': 'tmpXyz',
      'resourceExt': 'png',
      'savedPath': savedPath,
    };

void _install(_FakeAdapter adapter) {
  ApiService().dio.httpClientAdapter = adapter;
}

/// 页面是长滚动布局 / 500 宽的 ListView：默认 800×600 视口里上传按钮在屏幕外，
/// 点击会落空（点到的不是按钮）。放大视口让整页一次渲染出来。
void _desktopViewport(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 2000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    // AuthService 走 flutter_secure_storage（测试环境 kIsWeb=false）
    FlutterSecureStorage.setMockInitialValues({'access_token': 'tok-123', 'refresh_token': 'r-1'});
  });

  group('UserFile 存库形态', () {
    test('托管件＝相对 URL；历史绝对 URL / 空值 / null 都判为非托管', () {
      expect(UserFile.storedUrl('aB12_tmpXyz.png'), '/api/v1/user/file/aB12_tmpXyz.png');
      expect(UserFile.isManaged('/api/v1/user/file/aB12_tmpXyz.png'), isTrue);
      expect(UserFile.savedPathOf('/api/v1/user/file/aB12_tmpXyz.png'), 'aB12_tmpXyz.png');
      // 存量第三方 OAuth 头像：认不出前缀 ⇒ 当普通 URL 直接渲染
      expect(UserFile.isManaged('https://cdn.example.com/u/1.png'), isFalse);
      expect(UserFile.savedPathOf('https://cdn.example.com/u/1.png'), isNull);
      expect(UserFile.isManaged(''), isFalse);
      expect(UserFile.isManaged(null), isFalse);
    });
  });

  group('UserFile.uploadImage 协议', () {
    test('两步：preprocess 表单字段 + uploading 的 chunk_index 从 1 起，token 自动带', () async {
      final adapter = _FakeAdapter((o) async {
        if (o.uri.path.endsWith('/preprocess')) return _json(_preprocessOk());
        return _json({'error': 0, 'savedPath': 'aB12_tmpXyz.png'});
      });
      _install(adapter);

      final saved = await UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1, 2, 3]));
      expect(saved, 'aB12_tmpXyz.png');
      expect(adapter.requests.length, 2);

      final pre = adapter.requests[0];
      expect(pre.method, 'POST');
      expect(pre.uri.path, '/api/v1/aetherupload/preprocess');
      // 普通表单：插件读 $_POST，发 JSON 会拿不到字段
      expect(pre.headers[Headers.contentTypeHeader].toString(), contains('application/x-www-form-urlencoded'));
      expect(pre.data, {
        'resource_name': 'a.png',
        'resource_size': 3,
        // 秒传关着（instant_completion=false）：服务端只要求 present，空串是约定取值
        'resource_hash': '',
        'group': 'image',
        'locale': 'en',
      });

      final up = adapter.requests[1];
      expect(up.uri.path, '/api/v1/aetherupload/uploading');
      final form = up.data as FormData;
      expect(Map.fromEntries(form.fields), {
        'resource_ext': 'png',
        'chunk_total': '1',
        // 服务端 ctype_digit 后要求 ≥1，传 0 会被判 invalid_resource_params
        'chunk_index': '1',
        'resource_temp_basename': 'tmpXyz',
        'group': 'image',
        'group_subdir': 'aB12',
        'locale': 'en',
        'resource_hash': '',
      });
      expect(form.files.single.key, 'resource_chunk');
      expect(form.files.single.value.filename, 'a.png');
      expect(form.files.single.value.length, 3);

      // 两个请求都带上了 ApiService 拦截器注入的 token
      for (final r in adapter.requests) {
        expect(r.headers['Authorization'], 'Bearer tok-123');
      }
    });

    test('界面语言为中文时 locale 传 zh-CN（插件错误串按它本地化）', () async {
      Get.locale = const Locale('zh');
      addTearDown(() => Get.locale = null);
      final adapter = _FakeAdapter((o) async => o.uri.path.endsWith('/preprocess')
          ? _json(_preprocessOk())
          : _json({'error': 0, 'savedPath': 'x.png'}));
      _install(adapter);

      await UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1]));
      expect((adapter.requests[0].data as Map)['locale'], 'zh-CN');
      expect(Map.fromEntries((adapter.requests[1].data as FormData).fields)['locale'], 'zh-CN');
    });

    test('秒传命中（preprocess 直接回 savedPath）不再发第二个请求', () async {
      final adapter = _FakeAdapter((o) async => _json(_preprocessOk(savedPath: 'quick.png')));
      _install(adapter);

      expect(await UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1])), 'quick.png');
      expect(adapter.requests.length, 1);
    });

    test('插件报错原样透出（是人话不是错误码），且不产生半截上传', () async {
      final adapter = _FakeAdapter((o) async => _json({..._preprocessOk(), 'error': '文件过大'}));
      _install(adapter);

      await expectLater(
        UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1])),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', '文件过大')),
      );
      expect(adapter.requests.length, 1);
    });

    test('第二个请求报错同样透出，失败时不返回路径', () async {
      final adapter = _FakeAdapter((o) async => o.uri.path.endsWith('/preprocess')
          ? _json(_preprocessOk())
          : _json({'error': '写入失败', 'savedPath': ''}));
      _install(adapter);

      await expectLater(
        UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1])),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', '写入失败')),
      );
    });

    test('error 缺失或 savedPath 为空都当失败（fail-closed，不写读不回来的值）', () async {
      final missingError = _FakeAdapter((o) async => _json({'savedPath': ''}));
      _install(missingError);
      await expectLater(
        UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1])),
        throwsA(isA<ApiException>()),
      );

      final emptyPath = _FakeAdapter((o) async => o.uri.path.endsWith('/preprocess')
          ? _json(_preprocessOk())
          : _json({'error': 0, 'savedPath': ''}));
      _install(emptyPath);
      await expectLater(
        UserFile.uploadImage(fileName: 'a.png', bytes: Uint8List.fromList([1])),
        throwsA(isA<ApiException>()),
      );
    });
  });

  group('UserFile.fetchBytes 鉴权读取', () {
    test('200 + 图片 ⇒ 字节原样返回，且请求带 token', () async {
      final adapter = _FakeAdapter((o) async => _png(kPng));
      _install(adapter);

      expect(await UserFile.fetchBytes('aB12_tmpXyz.png'), kPng);
      expect(adapter.requests.single.uri.path, '/api/v1/user/file/aB12_tmpXyz.png');
      expect(adapter.requests.single.headers['Authorization'], 'Bearer tok-123');
      expect(adapter.requests.single.responseType, ResponseType.bytes);
    });

    test('403 是 HTTP 200 信封 ⇒ 按 content-type 分流成异常，绝不把 JSON 当图片字节', () async {
      final adapter = _FakeAdapter((o) async => _json({'code': 403, 'message': '无权访问该文件', 'data': []}));
      _install(adapter);

      await expectLater(
        UserFile.fetchBytes('otherUser.png'),
        throwsA(isA<ApiException>()
            .having((e) => e.code, 'code', 403)
            .having((e) => e.message, 'message', '无权访问该文件')),
      );
    });
  });

  group('UserFileImage 渲染分支', () {
    testWidgets('托管件带 token 取字节 ⇒ MemoryImage；历史绝对 URL ⇒ NetworkImage', (tester) async {
      final adapter = _FakeAdapter((o) async => _png(kPng));
      _install(adapter);

      await tester.pumpWidget(const MaterialApp(
        home: UserFileImage(stored: '/api/v1/user/file/aB12_tmpXyz.png', width: 40, height: 40),
      ));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(adapter.requests.length, 1);
      expect(tester.widget<Image>(find.byType(Image)).image, isA<MemoryImage>());

      await tester.pumpWidget(const MaterialApp(
        home: UserFileImage(stored: 'https://cdn.example.com/u/1.png', width: 40, height: 40),
      ));
      await tester.pump();
      expect(tester.widget<Image>(find.byType(Image)).image, isA<NetworkImage>());
      // 非托管件不该再去问本端接口
      expect(adapter.requests.length, 1);
    });

    testWidgets('空值 / 读取失败都落到占位图，不渲染空白', (tester) async {
      final adapter = _FakeAdapter((o) async => _json({'code': 403, 'message': '无权访问该文件'}));
      _install(adapter);

      await tester.pumpWidget(const MaterialApp(
        home: UserFileImage(stored: '', width: 40, height: 40),
      ));
      expect(find.byIcon(Icons.image_outlined), findsOneWidget);
      expect(adapter.requests, isEmpty);

      await tester.pumpWidget(const MaterialApp(
        home: UserFileImage(stored: '/api/v1/user/file/other.png', width: 40, height: 40),
      ));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.byIcon(Icons.image_outlined), findsOneWidget);
      expect(find.byType(Image), findsNothing);
    });
  });

  group('选图端口与路由接线', () {
    test('非 web（用例跑在 VM）选图端口恒返回 null ⇒ 界面按「用户取消」处理', () async {
      // 条件导入在 VM 上解析到 stub；若解析错（编到 dart:js_interop）整个用例文件根本加载不了
      expect(await UserFile.pickFromDevice(), isNull);
    });

    test('两页的路由把真实选图实现接上了（不是留个空钩子）', () {
      final profile = AppPages.routes.firstWhere((r) => r.name == '/profile');
      final identity = AppPages.routes.firstWhere((r) => r.name == '/identity');
      expect((profile.page() as ProfilePage).pickImage, UserFile.pickFromDevice);
      expect((identity.page() as IdentityPage).pickImage, UserFile.pickFromDevice);
    });
  });

  group('页面接线', () {
    Future<ResponseBody> pageRouter(RequestOptions o) async {
      if (o.uri.path == '/api/v1/user/profile' && o.method == 'GET') {
        return _json({
          'code': 0,
          'message': 'ok',
          'data': {'username': 'u1', 'nickname': 'n1', 'avatar': '', 'language': 'en'},
        });
      }
      if (o.uri.path == '/api/v1/user/identity/status') {
        return _json({'code': 0, 'message': 'ok', 'data': {'status': 'not_submitted'}});
      }
      if (o.uri.path.endsWith('/preprocess')) return _json(_preprocessOk());
      if (o.uri.path.endsWith('/uploading')) return _json({'error': 0, 'savedPath': 'aB12_tmpXyz.png'});
      if (o.uri.path.startsWith('/api/v1/user/file/')) return _png(kPng);
      return _json({'code': 404, 'message': 'not found'});
    }

    testWidgets('头像页：选图 → 两步上传 → 字段写成相对 URL，预览走鉴权字节', (tester) async {
      _desktopViewport(tester);
      final adapter = _FakeAdapter(pageRouter);
      _install(adapter);

      await tester.pumpWidget(GetMaterialApp(
        home: ProfilePage(pickImage: () async => PickedImage('a.png', Uint8List.fromList([1, 2, 3]))),
      ));
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(OutlinedButton, 'Upload'));
      // 上传两步 + 预览取字节都是真实异步链：一次 pump 排不干净（残留的 dio 超时 Timer
      // 会在拆卸时报「A Timer is still pending」）
      await tester.pumpAndSettle();

      // 存库值＝相对 URL，保存仍由用户按「保存」提交
      expect(find.text('/api/v1/user/file/aB12_tmpXyz.png'), findsOneWidget);
      expect(tester.widget<Image>(find.byType(Image)).image, isA<MemoryImage>());
    });

    testWidgets('头像页：未注入选图能力时不渲染上传按钮（不摆按不动的按钮）', (tester) async {
      _desktopViewport(tester);
      final adapter = _FakeAdapter(pageRouter);
      _install(adapter);

      await tester.pumpWidget(const GetMaterialApp(home: ProfilePage()));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(OutlinedButton, 'Upload'), findsNothing);
    });

    testWidgets('KYC 页：三照各自的按钮上传后写回各自字段', (tester) async {
      _desktopViewport(tester);
      final adapter = _FakeAdapter(pageRouter);
      _install(adapter);

      await tester.pumpWidget(GetMaterialApp(
        home: IdentityPage(pickImage: () async => PickedImage('id.png', Uint8List.fromList([9, 9]))),
      ));
      await tester.pumpAndSettle();

      expect(find.widgetWithText(OutlinedButton, 'Upload'), findsNWidgets(3));
      await tester.tap(find.widgetWithText(OutlinedButton, 'Upload').first);
      await tester.pumpAndSettle();

      expect(find.text('/api/v1/user/file/aB12_tmpXyz.png'), findsOneWidget);
    });
  });
}
