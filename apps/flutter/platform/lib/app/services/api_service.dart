// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response;
import 'auth_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._();
  factory ApiService() => _instance;

  late final Dio dio;
  static const String baseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://localhost:8792');

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
        final token = await AuthService.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          final refreshed = await tryRefresh();
          if (refreshed) {
            handler.resolve(await dio.fetch(error.requestOptions));
            return;
          }
          await AuthService.clearToken();
          _redirectToLoginOnce();
        }
        handler.next(error);
      },
    ));
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? params}) =>
      _request(() => dio.get(path, queryParameters: params));

  Future<Map<String, dynamic>> post(String path, {dynamic data}) => _request(() => dio.post(path, data: data));

  Future<Map<String, dynamic>> put(String path, {dynamic data}) => _request(() => dio.put(path, data: data));

  Future<Map<String, dynamic>> delete(String path, {dynamic data}) => _request(() => dio.delete(path, data: data));

  /// 服务端 json() 信封恒为 HTTP 200，鉴权失败体现在 body.code，HTTP 状态拦截器看不到；
  /// 此处在信封层识别 code 401：刷新后原样重发一次，仍失败则清 token 回登录页
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

  Map<String, dynamic> _handleResponse(Response resp) {
    final body = resp.data as Map<String, dynamic>;
    if (body['code'] != 0) {
      throw ApiException(body['code'] as int, body['message'] as String? ?? 'requestFailed');
    }
    return body;
  }

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
