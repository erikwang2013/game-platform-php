// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// ApiService.list 的协议层自检：**三种分页参数名一次发全** + 三种回包形状的取数。
// 用假 adapter（不是假 Dio）—— 这样断言的是真正发出去的 query，而不是我们以为发了什么。
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/app/services/api_service.dart';
import 'test_helpers.dart';

class _RecordingAdapter implements HttpClientAdapter {
  _RecordingAdapter(this.body);

  final Map<String, dynamic> body;
  final calls = <({String path, Map<String, dynamic> query})>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    calls.add((path: options.path, query: options.queryParameters));
    return ResponseBody.fromString(jsonEncode(body), 200, headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized(); // 协议层用例不是 testWidgets，绑定要自己起
  setUp(setUpTest);

  late HttpClientAdapter original;
  setUp(() => original = ApiService().dio.httpClientAdapter);
  tearDown(() => ApiService().dio.httpClientAdapter = original);

  group('分页参数扇出', () {
    test('一次发出 page + page_size + limit + size + per_page 五个键', () async {
      final adapter = _RecordingAdapter(<String, dynamic>{
        'code': 0,
        'data': <String, dynamic>{'list': <dynamic>[], 'total': 0, 'page': 3, 'limit': 20},
      });
      ApiService().dio.httpClientAdapter = adapter;

      await ApiService().list('/admin/v1/user', page: 3, pageSize: 20);

      expect(adapter.calls.single.path, '/admin/v1/user');
      final q = adapter.calls.single.query;
      // 后端三种方言各读一个：limit（15 个控制器）/ size（风控 7 个）/ per_page（SearchController）。
      // 少发任一个 ⇒ 那些控制器回落自己的缺省值，而前端按 pageSize 算页数 ⇒ 尾页整段取不到。
      expect(q['page'], 3);
      expect(q['page_size'], 20);
      expect(q['limit'], 20);
      expect(q['size'], 20);
      expect(q['per_page'], 20);
    });

    test('pageSize 传别的值也全都跟着走（不是写死的 20）', () async {
      final adapter = _RecordingAdapter(<String, dynamic>{'code': 0, 'data': <String, dynamic>{'items': <dynamic>[], 'total': 0}});
      ApiService().dio.httpClientAdapter = adapter;

      await ApiService().list('/admin/v1/risk/rule/list', page: 2, pageSize: 50);

      final q = adapter.calls.single.query;
      expect(q['page'], 2);
      for (final key in <String>['page_size', 'limit', 'size', 'per_page']) {
        expect(q[key], 50, reason: '$key 必须跟着 pageSize 走');
      }
    });

    test('业务筛选参数与分页参数并存，不被分页键挤掉', () async {
      final adapter = _RecordingAdapter(<String, dynamic>{'code': 0, 'data': <String, dynamic>{'list': <dynamic>[], 'total': 0}});
      ApiService().dio.httpClientAdapter = adapter;

      await ApiService().list('/admin/v1/identity/list',
          page: 1, pageSize: 15, params: <String, dynamic>{'status': 'pending', 'keyword': 'abc'});

      final q = adapter.calls.single.query;
      expect(q['status'], 'pending');
      expect(q['keyword'], 'abc');
      expect(q['page'], 1);
      expect(q['limit'], 15);
    });
  });

  group('回包形状', () {
    Future<({List<dynamic> rows, int total})> call(Map<String, dynamic> body) async {
      ApiService().dio.httpClientAdapter = _RecordingAdapter(body);
      return ApiService().list('/admin/v1/x', page: 1, pageSize: 15);
    }

    test('多数派：data.{list,total}', () async {
      final r = await call(<String, dynamic>{
        'code': 0,
        'data': <String, dynamic>{'list': <dynamic>[<String, dynamic>{'id': 'a'}], 'total': 42},
      });
      expect(r.rows.length, 1);
      expect(r.total, 42);
    });

    test('风控族：data.{total,items}（行键不同，total 同款）', () async {
      final r = await call(<String, dynamic>{
        'code': 0,
        'data': <String, dynamic>{'items': <dynamic>[<String, dynamic>{'id': 'a'}, <String, dynamic>{'id': 'b'}], 'total': 7},
      });
      expect(r.rows.length, 2);
      expect(r.total, 7);
    });

    test('裸数组（本就不分页的端点）：total 退化成行数，不抛', () async {
      final r = await call(<String, dynamic>{'code': 0, 'data': <dynamic>[<String, dynamic>{'id': 'a'}]});
      expect(r.rows.length, 1);
      expect(r.total, 1);
    });

    test('没有 total：按本页行数兜底（宁可少算页数，也不崩在类型转换上）', () async {
      final r = await call(<String, dynamic>{
        'code': 0,
        'data': <String, dynamic>{'list': <dynamic>[<String, dynamic>{'id': 'a'}, <String, dynamic>{'id': 'b'}]},
      });
      expect(r.rows.length, 2);
      expect(r.total, 2);
    });

    test('total 不是数字 ⇒ 退化成本页行数，不硬转崩掉', () async {
      final r = await call(<String, dynamic>{
        'code': 0,
        'data': <String, dynamic>{'list': <dynamic>[<String, dynamic>{'id': 'a'}], 'total': '23'},
      });
      expect(r.total, 1, reason: '认不出的 total 退化成行数，不能把字符串硬转成 int 崩掉');
    });
  });
}
