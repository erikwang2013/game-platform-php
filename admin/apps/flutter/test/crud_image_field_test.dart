// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// image 字段 + 图片直传的自检，三段：
// ① 协议层（ApiService.uploadImage 打的是不是后端那两步、字段名/形状对不对、error 怎么读）；
// ② 底座（crud.dart 的 image 分支：写回 URL、上传中禁用、失败原样显示、取消不动值）；
// ③ 接线（三棵树的字段确实声明成了 image —— 组件层绿不代表页面挂上了）。
import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/achievement/achievement_page.dart';
import 'package:admin_app/app/pages/game/game_list_page.dart';
import 'package:admin_app/app/pages/game_category/game_category_page.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'package:admin_app/app/widgets/crud.dart';
import 'test_helpers.dart';

/// 记下每个请求的 path / content-type / 原始 body，并按剧本回包。
/// 用假 adapter 而不是假 Dio：这样连「表单编码 / multipart 形状」都在断言范围内。
class _FakeAetherAdapter implements HttpClientAdapter {
  _FakeAetherAdapter(this.respond);

  final Map<String, dynamic> Function(int index) respond;
  final calls = <({String path, String contentType, String body})>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final bytes = <int>[];
    if (requestStream != null) {
      await for (final part in requestStream) {
        bytes.addAll(part);
      }
    }
    calls.add((
      path: options.path,
      contentType: _header(options.headers, 'content-type'),
      body: utf8.decode(bytes, allowMalformed: true),
    ));
    // 真后端的响应头是 application/json（webman 的 json()），少了它 dio 就不会解码成 Map
    return ResponseBody.fromString(jsonEncode(respond(calls.length - 1)), 200,
        headers: <String, List<String>>{
          Headers.contentTypeHeader: <String>[Headers.jsonContentType],
        });
  }

  @override
  void close({bool force = false}) {}

  static String _header(Map<String, dynamic> headers, String name) {
    for (final entry in headers.entries) {
      if (entry.key.toLowerCase() == name) return entry.value.toString();
    }
    return '';
  }
}

Map<String, dynamic> _preprocessOk({String savedPath = ''}) => <String, dynamic>{
      'error': 0,
      'chunkSize': 1000000,
      'groupSubDir': '202610',
      'resourceTempBaseName': 'tmp123',
      'resourceExt': 'png',
      'savedPath': savedPath,
    };

Map<String, dynamic> _uploadOk(String savedPath) => <String, dynamic>{'error': 0, 'savedPath': savedPath};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized(); // 协议层的用例不是 testWidgets，绑定要自己起
  setUp(setUpTest);

  group('协议层：ApiService.uploadImage', () {
    late HttpClientAdapter original;

    setUp(() => original = ApiService().dio.httpClientAdapter);
    tearDown(() => ApiService().dio.httpClientAdapter = original);

    test('两步：preprocess 是普通表单、uploading 是单块 multipart，返回绝对展示 URL', () async {
      final adapter = _FakeAetherAdapter((i) => i == 0 ? _preprocessOk() : _uploadOk('image_202610_abc.png'));
      ApiService().dio.httpClientAdapter = adapter;

      final url = await ApiService().uploadImage(Uint8List.fromList(<int>[1, 2, 3]), 'cover.png');

      expect(url, '${ApiService.baseUrl}/admin/v1/aetherupload/display/image_202610_abc.png');
      expect(adapter.calls.length, 2);
      expect(adapter.calls[0].path, '/admin/v1/aetherupload/preprocess');

      // ① 预检必须是 **表单**（服务端从 $_POST 取；JSON 体在 workerman 下走的是另一条解析路径）
      expect(adapter.calls[0].contentType, contains(Headers.formUrlEncodedContentType));
      expect(adapter.calls[0].body, contains('resource_name=cover.png'));
      expect(adapter.calls[0].body, contains('resource_size=3'));
      expect(adapter.calls[0].body, contains('resource_hash=')); // 空秒传：契约要求 present
      expect(adapter.calls[0].body, contains('locale=zh_CN'));
      expect(adapter.calls[0].body, contains('group=image'));

      // ② 分块必须是 multipart、单块（index 从 1 起），且 group_subdir/ext/临时名来自**预检的返回**
      final chunk = adapter.calls[1];
      expect(chunk.path, '/admin/v1/aetherupload/uploading');
      expect(chunk.contentType, contains('multipart/form-data'));
      expect(chunk.body, contains('name="resource_chunk"'));
      expect(chunk.body, contains('filename="cover.png"'));
      expect(RegExp(r'name="chunk_index"\r\n\r\n1\r\n').hasMatch(chunk.body), isTrue);
      expect(RegExp(r'name="chunk_total"\r\n\r\n1\r\n').hasMatch(chunk.body), isTrue);
      expect(RegExp(r'name="resource_ext"\r\n\r\npng\r\n').hasMatch(chunk.body), isTrue);
      expect(RegExp(r'name="group_subdir"\r\n\r\n202610\r\n').hasMatch(chunk.body), isTrue);
      expect(RegExp(r'name="resource_temp_basename"\r\n\r\ntmp123\r\n').hasMatch(chunk.body), isTrue);
      expect(RegExp(r'name="group"\r\n\r\nimage\r\n').hasMatch(chunk.body), isTrue);
    });

    test('秒传命中（savedPath 非空）直接完成，不再发第二个请求', () async {
      final adapter = _FakeAetherAdapter((_) => _preprocessOk(savedPath: 'image_202610_hit.png'));
      ApiService().dio.httpClientAdapter = adapter;

      final url = await ApiService().uploadImage(Uint8List.fromList(<int>[1]), 'a.png');

      expect(adapter.calls.length, 1); // 本仓 instant_completion=false，但契约里有这条
      expect(url, endsWith('/admin/v1/aetherupload/display/image_202610_hit.png'));
    });

    test('error 非 0 → 抛出服务端原文，且不上传分块', () async {
      final adapter = _FakeAetherAdapter((_) => <String, dynamic>{..._preprocessOk(), 'error': '错误：无效的文件类型'});
      ApiService().dio.httpClientAdapter = adapter;

      await expectLater(
        ApiService().uploadImage(Uint8List.fromList(<int>[1]), 'a.svg'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', '错误：无效的文件类型')),
      );
      expect(adapter.calls.length, 1); // 预检就失败了，不该再发分块
    });

    test('响应不是 aetherupload 形状（鉴权信封：没有 error 字段）→ 抛信封 message 而不是 TypeError', () async {
      // 中间件拒绝时回的是本仓 {code, message} 信封；走 _handleResponse 会把缺字段读成非 0 而炸
      final adapter = _FakeAetherAdapter((_) => <String, dynamic>{'code': 401, 'message': '登录已过期'});
      ApiService().dio.httpClientAdapter = adapter;

      await expectLater(
        ApiService().uploadImage(Uint8List.fromList(<int>[1]), 'a.png'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', '登录已过期')),
      );
    });

    test('分块回包没有 savedPath（协议违例）→ 抛错而不是落一条空 URL', () async {
      final adapter = _FakeAetherAdapter((i) => i == 0 ? _preprocessOk() : _uploadOk(''));
      ApiService().dio.httpClientAdapter = adapter;

      await expectLater(ApiService().uploadImage(Uint8List.fromList(<int>[1]), 'a.png'), throwsA(isA<ApiException>()));
    });
  });

  group('底座：CrudFieldType.image', () {
    Future<void> openForm(
      WidgetTester tester, {
      required CrudImageUpload imageUpload,
      Future<void> Function(Map<String, dynamic>)? onSubmit,
      Map<String, dynamic>? initial,
      String label = 'cover_image',
      String key = 'game.cover_image',
    }) async {
      tester.view.physicalSize = const Size(1400, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(GetMaterialApp(
        locale: const Locale('en', 'US'),
        home: Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => showCrudForm(
              context,
              title: 'Form',
              fields: <CrudField>[CrudField(label, key, type: CrudFieldType.image)],
              initial: initial,
              imageUpload: imageUpload,
              onSubmit: onSubmit ?? (data) async {},
            ),
            child: const Text('open'),
          ),
        ),
      ));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();
    }

    Finder uploadButton() => find.widgetWithText(ElevatedButton, 'Upload');

    testWidgets('上传成功：URL 写回文本框、出现缩略图，并随提交进 payload', (tester) async {
      Map<String, dynamic>? submitted;
      await openForm(
        tester,
        imageUpload: () async => 'http://admin.games.test/admin/v1/aetherupload/display/image_202610_x.png',
        onSubmit: (data) async => submitted = data,
      );

      await tester.tap(uploadButton());
      await tester.pumpAndSettle();

      expect(find.widgetWithText(TextField, 'http://admin.games.test/admin/v1/aetherupload/display/image_202610_x.png'),
          findsOneWidget);
      expect(find.byType(Image), findsOneWidget); // 缩略图
      expect(tester.takeException(), isNull); // 用例里网络恒 400：errorBuilder 必须吃掉它

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(submitted!['cover_image'],
          'http://admin.games.test/admin/v1/aetherupload/display/image_202610_x.png');
    });

    testWidgets('上传中按钮禁用并转圈，完成后恢复', (tester) async {
      final gate = Completer<String?>();
      await openForm(tester, imageUpload: () => gate.future);

      await tester.tap(uploadButton());
      await tester.pump(); // 不 pumpAndSettle：转圈是无限动画，会超时

      expect(tester.widget<ElevatedButton>(uploadButton()).onPressed, isNull);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      // 文本框也一起禁用：否则正在改的字会被上传结果顶掉
      expect(tester.widget<TextField>(find.byType(TextField)).enabled, isFalse);

      gate.complete('http://admin.games.test/admin/v1/aetherupload/display/done.png');
      await tester.pump();
      await tester.pump();

      expect(tester.widget<ElevatedButton>(uploadButton()).onPressed, isNotNull);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(tester.widget<TextField>(find.byType(TextField)).enabled, isTrue);
    });

    testWidgets('上传失败：服务端 error 原文显示在框内、不关框、值不动', (tester) async {
      var calls = 0;
      await openForm(
        tester,
        imageUpload: () async {
          calls++;
          throw ApiException(-1, '错误：无效的文件类型');
        },
        onSubmit: (data) async => calls++,
      );

      await tester.tap(uploadButton());
      await tester.pumpAndSettle();

      expect(find.text('错误：无效的文件类型'), findsOneWidget);
      expect(find.text('Form'), findsOneWidget); // 不关框
      expect(calls, 1); // 只是上传失败，没有触发提交

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(calls, 2); // 失败不挡提交（空值照发，服务端说了算）
    });

    testWidgets('用户取消（动作返回 null）：不写值、不报错，按钮恢复可点', (tester) async {
      await openForm(tester, imageUpload: () async => null);

      await tester.tap(uploadButton());
      await tester.pumpAndSettle();

      expect(find.byType(Image), findsNothing);
      expect(find.text('Form'), findsOneWidget);
      expect(tester.widget<ElevatedButton>(uploadButton()).onPressed, isNotNull);
    });

    testWidgets('存量手输 URL：预填进文本框、原样提交（编辑态不被上传按钮破坏）', (tester) async {
      Map<String, dynamic>? submitted;
      await openForm(
        tester,
        imageUpload: () async => 'http://new/x.png',
        initial: <String, dynamic>{'cover_image': 'http://legacy/hand-typed.png'},
        onSubmit: (data) async => submitted = data,
      );

      expect(find.widgetWithText(TextField, 'http://legacy/hand-typed.png'), findsOneWidget);

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(submitted!['cover_image'], 'http://legacy/hand-typed.png');
    });

    testWidgets('编辑态置灰的 image 字段：上传按钮一起禁用', (tester) async {
      tester.view.physicalSize = const Size(1400, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(GetMaterialApp(
        locale: const Locale('en', 'US'),
        home: Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => showCrudForm(
              context,
              title: 'Form',
              fields: const <CrudField>[
                CrudField('cover_image', 'game.cover_image', type: CrudFieldType.image, editableOnEdit: false),
              ],
              initial: <String, dynamic>{'cover_image': 'http://legacy/x.png'},
              imageUpload: () async => 'http://new/x.png',
              onSubmit: (data) async {},
            ),
            child: const Text('open'),
          ),
        ),
      ));
      await tester.tap(find.text('open'));
      await tester.pumpAndSettle();

      expect(tester.widget<ElevatedButton>(uploadButton()).onPressed, isNull);
    });
  });

  group('接线：三棵树都声明成了 image', () {
    Future<void> openCreateForm(WidgetTester tester, Widget page) async {
      tester.view.physicalSize = const Size(1400, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(GetMaterialApp(
        locale: const Locale('en', 'US'),
        home: Scaffold(body: page),
      ));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Create'));
      await tester.pumpAndSettle();
    }

    testWidgets('游戏列表：cover_image 是 image 字段（有上传按钮）', (tester) async {
      Get.put<GameListController>(_FakeGameListController());
      await openCreateForm(tester, const GameListPage());

      expect(find.widgetWithText(ElevatedButton, 'Upload'), findsOneWidget);
      expect(find.text('Cover Image URL'), findsOneWidget); // 原文本框还在
    });

    testWidgets('游戏分类：icon 是 image 字段', (tester) async {
      Get.put<GameCategoryAdminController>(_FakeCategoryController());
      await openCreateForm(tester, const GameCategoryPage());

      expect(find.widgetWithText(ElevatedButton, 'Upload'), findsOneWidget);
    });

    testWidgets('成就：icon 是 image 字段', (tester) async {
      Get.put<AchievementAdminController>(_FakeAchievementController());
      await openCreateForm(tester, const AchievementPage());

      expect(find.widgetWithText(ElevatedButton, 'Upload'), findsOneWidget);
    });
  });
}

// 列表一律不打网络（离线用例）：只覆盖 load 这一处副作用入口。
class _FakeGameListController extends GameListController {
  @override
  Future<void> load({int? toPage}) async {}
}

class _FakeCategoryController extends GameCategoryAdminController {
  @override
  Future<void> load() async {}
}

class _FakeAchievementController extends AchievementAdminController {
  @override
  Future<void> load() async {}
}
