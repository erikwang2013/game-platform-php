/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'dart:typed_data';

import 'package:dio/dio.dart';
// get 自带一套同名 multipart 类型（get_connect），这里只要 dio 的
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'auth_service.dart';
import '../i18n/locale_controller.dart';

/// 图片直传的组名：本树只开 `image` 一组（后端 groups.image 白名单 jpg/jpeg/png/gif/webp、≤5MB）。
const String aetherImageGroup = 'image';

class ApiService {
  static final ApiService _instance = ApiService._();
  factory ApiService() => _instance;

  late final Dio dio;
  static const String baseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://admin.games.test');

  ApiService._() {
    dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {
        'Content-Type': 'application/json',
      },
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // 语言必须先于业务请求发出去：后端 `LanguageMiddleware` 按 `X-Language` → `Accept-Language`
        // → 默认(zh) 决定 `trans()` 用哪张表。**不发这个头，界面切到日语也只是客户端文案变，
        // 服务端 message 永远是中文** —— 「支持 13 种语言」只落一半。
        // 值用短码（后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码）。
        options.headers['X-Language'] = LocaleController.currentCode;

        final token = await AuthService.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          final refreshed = await tryRefresh();
          if (!refreshed) {
            await AuthService.clearToken();
            Future.microtask(() => Get.offAllNamed('/login'));
          }
        }
        handler.next(error);
      },
    ));
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? params}) =>
      _request(() => dio.get(path, queryParameters: params));

  /// 列表分页取数：返回 (行数组, 总数)，并**把三种分页参数名一次发全**。
  ///
  /// 后端读分页参数的口径不统一：`limit`（15 个控制器，缺省 15）、`size`（7 个风控类，
  /// 缺省 20）、`per_page`（SearchController）。只发 `page_size` 谁都不认识，服务端就退回
  /// 自己的缺省值，而前端按自己的 pageSize 算页数 ⇒ 尾页永远取不到
  /// （total=100、每页 20 时第 76~100 条不可达）。别名全发，各控制器读它认识的那个
  /// （与 angular 树 core/api.service.ts 的 `list()` 同法、同理）。
  ///
  /// 行数组的键也不统一：多数是 `data.list`、风控七个是 `data.items`；裸数组（本就不分页）
  /// 也容错。`total` 缺失时按本页行数兜底 —— 宁可少算页数，也不要让 pager 崩在类型转换上。
  Future<({List<dynamic> rows, int total})> list(
    String path, {
    int page = 1,
    int pageSize = 20,
    Map<String, dynamic>? params,
  }) async {
    final query = <String, dynamic>{
      ...?params,
      'page': page,
      'page_size': pageSize,
      'limit': pageSize,
      'size': pageSize,
      'per_page': pageSize,
    };
    final resp = await get(path, params: query);
    final data = resp['data'];
    if (data is List) return (rows: data, total: data.length);
    final body = data is Map ? data : const <String, dynamic>{};
    final rows = body['list'] ?? body['items'];
    final list = rows is List ? rows : const <dynamic>[];
    final total = body['total'];
    return (rows: list, total: total is num ? total.toInt() : list.length);
  }

  Future<Map<String, dynamic>> post(String path, {dynamic data}) => _request(() => dio.post(path, data: data));

  Future<Map<String, dynamic>> put(String path, {dynamic data}) => _request(() => dio.put(path, data: data));

  Future<Map<String, dynamic>> delete(String path, {dynamic data}) => _request(() => dio.delete(path, data: data));

  /// 图片上传（aetherupload 插件的两步协议），返回**可落库的绝对 URL**。
  ///
  /// 刻意不走 `_request`：该插件的响应体是裸的 `{error, chunkSize, ...}`，没有本仓
  /// `{code, message, data}` 信封，`_handleResponse` 会把恒缺的 code 读成「非 0」而误报错。
  /// JWT 仍由 dio 拦截器统一带（两个端点都要后台登录）。展示路由公开，故返回值是完整 URL。
  Future<String> uploadImage(Uint8List bytes, String fileName) async {
    // 第一步：预检（普通表单，不是 JSON —— 服务端从 $_POST 取值）
    final pre = await dio.post(
      '/admin/v1/aetherupload/preprocess',
      data: <String, dynamic>{
        'resource_name': fileName,
        'resource_size': bytes.length,
        'resource_hash': '', // 本仓关了秒传与完整性校验（lax_mode）⇒ 契约要求空串
        'locale': 'zh_CN',
        'group': aetherImageGroup,
      },
      options: Options(contentType: Headers.formUrlEncodedContentType),
    );
    final meta = _aetherResult(pre);
    final instant = _savedPath(meta);
    if (instant.isNotEmpty) return _displayUrl(instant); // 秒传命中即完成（本仓 instant_completion=false，走不到）

    // 第二步：整份作为**单块**送（chunk_total=1、index 从 1 起）。服务端按块数算进度，单块即 complete；
    // 大小上限由 service 端 filterBySize 逐块兜（≤5MB）。
    final up = await dio.post(
      '/admin/v1/aetherupload/uploading',
      data: FormData.fromMap(<String, dynamic>{
        'resource_chunk': MultipartFile.fromBytes(bytes, filename: fileName),
        'resource_ext': meta['resourceExt'] ?? '',
        'chunk_total': '1',
        'chunk_index': '1',
        'resource_temp_basename': meta['resourceTempBaseName'] ?? '',
        'group': aetherImageGroup,
        'group_subdir': meta['groupSubDir'] ?? '',
        'locale': 'zh_CN',
        'resource_hash': '',
      }),
    );
    final savedPath = _savedPath(_aetherResult(up));
    if (savedPath.isEmpty) throw ApiException(-1, '上传失败：服务端未返回文件路径');
    return _displayUrl(savedPath);
  }

  /// aetherupload 的响应判定：`error` 为 0（数字）才成功；非 0 是**已翻译好的成品文案**，
  /// 原样抛出（界面上直接显示）。鉴权中间件拒绝时回的是本仓另一套信封（没有 error 字段），
  /// 一并按失败处理并把 message 取出来。
  Map<String, dynamic> _aetherResult(Response resp) {
    final body = resp.data is Map ? Map<String, dynamic>.from(resp.data as Map) : <String, dynamic>{};
    final error = body['error'];
    if (error is num && error == 0) return body;
    throw ApiException(-1, error?.toString() ?? body['message']?.toString() ?? '上传失败');
  }

  String _savedPath(Map<String, dynamic> body) => body['savedPath']?.toString() ?? '';

  /// 落库值 = 对外基址 + 展示路由 + savedPath（savedPath 形如 `image_202609_<md5>.png`）
  String _displayUrl(String savedPath) => '$baseUrl/admin/v1/aetherupload/display/$savedPath';

  /// 服务端 json() 信封恒为 HTTP 200，鉴权失败体现在 body.code，上面的状态码拦截器看不到；
  /// 此处在信封层识别 code 401：刷新后原样重发一次，仍失败则清 token 回登录页。
  /// （判据与 C 端 apps/flutter/platform 同款：既判 HTTP 也判 body.code）
  Future<Map<String, dynamic>> _request(Future<Response> Function() send) async {
    var resp = await send();
    if (_isUnauthorized(resp) && await tryRefresh()) {
      resp = await send();
    }
    if (_isUnauthorized(resp)) {
      await AuthService.clearToken();
      _redirectToLoginOnce();
      throw ApiException(401, '登录已过期，请重新登录');
    }
    return _handleResponse(resp);
  }

  bool _isUnauthorized(Response resp) => resp.data is Map && resp.data['code'] == 401;

  Future<bool>? _refreshInFlight;
  static bool _loginRedirectPending = false;

  void _redirectToLoginOnce() {
    if (_loginRedirectPending) return;
    _loginRedirectPending = true;
    Future.microtask(() {
      Get.offAllNamed('/login');
      _loginRedirectPending = false;
    });
  }

  Map<String, dynamic> _handleResponse(Response resp) {
    final body = resp.data as Map<String, dynamic>;
    if (body['code'] != 0) {
      throw ApiException(body['code'] as int, body['message'] as String? ?? 'requestFailed');
    }
    return body;
  }

  /// 单飞：并发 401 只发一次刷新请求。refresh token 是一次性轮换的（旧 jti 立即入黑名单），
  /// 并发刷新会让后到的那次拿废票去换 token 而失败，进而误清 token 把用户登出。
  Future<bool> tryRefresh() {
    return _refreshInFlight ??= _doRefresh().whenComplete(() => _refreshInFlight = null);
  }

  Future<bool> _doRefresh() async {
    final refreshToken = await AuthService.getRefreshToken();
    if (refreshToken == null) return false;
    try {
      final resp = await dio.post('/api/v1/auth/refresh', data: {'refresh_token': refreshToken});
      final data = resp.data['data'];
      if (resp.data['code'] == 0) {
        // refresh 响应不含 user，沿用已存用户名，否则刷新后用户名会被清空
        final storedUsername = await AuthService.getUsername();
        await AuthService.saveLogin(
          token: data['access_token'],
          refreshToken: data['refresh_token'],
          username: data['user']?['username'] ?? storedUsername ?? '',
        );
        return true;
      }
    } catch (_) {}
    return false;
  }
}

class ApiException implements Exception {
  final int code;
  final String message;
  ApiException(this.code, this.message);

  @override
  String toString() => 'ApiException($code): $message';
}
