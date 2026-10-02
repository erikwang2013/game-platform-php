// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// 测试用 HTTP 打桩（形态同 user_file_test.dart 里的 `_FakeAdapter`）：
/// 装进 `ApiService().dio.httpClientAdapter`，按 path 决定应答。
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.handler);

  final Future<ResponseBody> Function(RequestOptions options) handler;
  final List<RequestOptions> requests = [];

  int callsTo(String pathSuffix) => requests.where((r) => r.uri.path.endsWith(pathSuffix)).length;

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

ResponseBody _envelope(Object? body, int code, String message) => ResponseBody.fromString(
      jsonEncode({'code': code, 'message': message, 'data': body}),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

/// 一律失败：任何请求立刻以 DioException 拒绝（等于断网，且不依赖真实 HTTP ——
/// 真实 socket 在 widget test 的 FakeAsync 里永远不会完成，只会把 pumpAndSettle 挂死）
FakeAdapter failingAdapter() =>
    FakeAdapter((o) => Future.error(DioException(requestOptions: o, message: 'offline')));

/// 服务端信封恒为 HTTP 200（成功）
ResponseBody jsonOk(Object body) => _envelope(body, 0, 'ok');

/// 业务失败也走 HTTP 200，错误码在 body.code（与 `ApiService._handleResponse` 一致）
ResponseBody jsonFail(int code, String message) => _envelope(null, code, message);
