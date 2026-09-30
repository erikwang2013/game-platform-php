// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
// FormData / MultipartFile 与 get 的同名类冲突，显式让位给 dio（get 那两个是自建 HTTP 客户端的）
import 'package:get/get.dart' hide Response, FormData, MultipartFile;

import '../i18n/translations.dart';
import 'api_service.dart';
// DOM 互操作挡在 web 实现里：VM 上的 widget 用例绝不会编到 dart:js_interop
// （无条件 import 会让整棵树的用例编不过），web 构建期才解析到真实现
import 'image_pick_stub.dart' if (dart.library.js_interop) 'image_pick_web.dart' as picker;

/// 用户自选图（头像 / KYC 三照）。
class PickedImage {
  /// 原始文件名：服务端 `pathinfo()` 取扩展名，扩展名白名单不过就直接拒
  final String fileName;
  final Uint8List bytes;

  const PickedImage(this.fileName, this.bytes);
}

/// 个人件（头像 / KYC 三照）的上传与读取。
///
/// **上传**走 aetherupload 两步式（preprocess → uploading），响应是插件自己的**扁平**
/// `{error, chunkSize, …}`——**不是**本平台 `{code, message, data}` 信封，所以不能走
/// `ApiService.get/post`（那会把缺失的 `code` 判成失败并抛 null 转型错），只能用原始 dio
/// （token 由 ApiService 的拦截器照常带上）。
///
/// **读取**不注册插件的 display 路由：那个端点只查「有没有登录」、不查 savedPath 属于谁，
/// KYC 证件照挂上去等于任何登录用户拿到路径就能看。个人件统一走
/// `GET /api/v1/user/file/{savedPath}`（头像登录即可读、KYC 三照仅归属人，见
/// `service/app/api/v1/controller/UserFileController.php`）⇒ 前端拿不到可直接渲染的 URL，
/// 必须带 token 取字节再用 `Image.memory` 显示。
class UserFile {
  /// 存库与读取共用的相对前缀（换路径只改这一处；服务端 URL_PREFIX 与它同形）
  static const String urlPrefix = '/api/v1/user/file/';

  /// 上传分组：插件按组白名单校验扩展名与大小（C 端只有 image 一组）
  static const String group = 'image';

  static const String _preprocessPath = '/api/v1/aetherupload/preprocess';
  static const String _uploadingPath = '/api/v1/aetherupload/uploading';

  /// 存库形态＝相对 URL（同源，换域名不用回填）
  static String storedUrl(String savedPath) => '$urlPrefix$savedPath';

  /// 是否本端托管件（决定渲染要不要带 token 取字节）。
  /// 存量值可能是第三方 OAuth 的**绝对 URL**，那类认不出前缀 ⇒ 当普通 URL 直接用。
  static bool isManaged(String? stored) => stored != null && stored.startsWith(urlPrefix);

  /// 托管件的 savedPath；非托管件为 null
  static String? savedPathOf(String? stored) =>
      isManaged(stored) ? stored!.substring(urlPrefix.length) : null;

  /// 插件错误串按它本地化（业务错误要原样展示给用户）：跟随界面语言，认不出按 en
  static String _pluginLocale() => (Get.locale?.languageCode ?? 'en') == 'zh' ? 'zh-CN' : 'en';

  /// 默认选图实现（浏览器弹系统选图框；非 web 平台恒返回 null ⇒ 界面按「用户取消」处理）。
  /// 直接把它当页面参数传：`ProfilePage(pickImage: UserFile.pickFromDevice)`（见 app_pages.dart）
  static Future<PickedImage?> pickFromDevice() => picker.pickImage();

  /// 上传一张图，返回 savedPath（落库前请套 [storedUrl]）。
  ///
  /// 单块上传：`chunk_total = chunk_index = 1`（**索引从 1 起**，服务端 `ctype_digit` 后
  /// 要求 ≥1，传 0 直接 invalid_resource_params）。
  static Future<String> uploadImage({
    required String fileName,
    required Uint8List bytes,
    String? locale,
  }) async {
    final dio = ApiService().dio;
    final pluginLocale = locale ?? _pluginLocale();

    final pre = await dio.post<dynamic>(
      _preprocessPath,
      data: {
        'resource_name': fileName,
        'resource_size': bytes.length,
        // 宽松模式（秒传关）：服务端只要求 present，空串是约定的取值
        'resource_hash': '',
        'group': group,
        'locale': pluginLocale,
      },
      options: Options(contentType: Headers.formUrlEncodedContentType),
    );
    final preData = _jsonMap(pre.data) ?? _throwUploadFailed();
    _throwIfPluginError(preData['error']);

    // 秒传命中：savedPath 非空即结束（本仓 instant_completion 关着，分支保留以免开关一开就断）
    final quick = preData['savedPath'];
    if (quick is String && quick.isNotEmpty) return quick;

    final form = FormData.fromMap({
      'resource_chunk': MultipartFile.fromBytes(bytes, filename: fileName),
      'resource_ext': '${preData['resourceExt'] ?? ''}',
      'chunk_total': '1',
      'chunk_index': '1',
      'resource_temp_basename': '${preData['resourceTempBaseName'] ?? ''}',
      'group': group,
      'group_subdir': '${preData['groupSubDir'] ?? ''}',
      'locale': pluginLocale,
      'resource_hash': '',
    });
    final up = await dio.post<dynamic>(_uploadingPath, data: form);
    final upData = _jsonMap(up.data) ?? _throwUploadFailed();
    _throwIfPluginError(upData['error']);

    final saved = upData['savedPath'];
    // 服务端没报错却没给路径：宁可不落库，也别写一个读不回来的值进去
    if (saved is! String || saved.isEmpty) _throwUploadFailed();
    return saved;
  }

  /// 带 token 取托管件字节。
  ///
  /// 拒绝（403 无权 / 404 不存在 / 401 未登录）走的是平台 **HTTP 200 信封**，
  /// 此时 body 是 JSON 不是图片 —— 必须按 content-type 分流，否则 JSON 会被当成图片字节
  /// 塞进 `Image.memory`（表现为一个解不出来的灰块，看不出是权限问题）。
  static Future<Uint8List> fetchBytes(String savedPath) async {
    final resp = await ApiService().dio.get<List<int>>(
      '$urlPrefix$savedPath',
      options: Options(responseType: ResponseType.bytes),
    );

    final contentType = resp.headers.value(Headers.contentTypeHeader) ?? '';
    if (contentType.contains('application/json')) {
      final body = _jsonMap(resp.data) ?? const <String, dynamic>{};
      throw ApiException(
        body['code'] is int ? body['code'] as int : -1,
        body['message'] is String ? body['message'] as String : '${AppTranslations.t('app.file_read_failed')}',
      );
    }
    return Uint8List.fromList(resp.data ?? const <int>[]);
  }

  /// 响应体 → Map；不是 JSON 对象（网关 HTML 等）返回 null，由调用方给人话
  static Map<String, dynamic>? _jsonMap(dynamic data) {
    if (data is Map) return Map<String, dynamic>.from(data);
    if (data is List<int>) {
      try {
        final decoded = jsonDecode(utf8.decode(data));
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } catch (_) {}
    }
    return null;
  }

  static Never _throwUploadFailed() =>
      throw ApiException(-1, '${AppTranslations.t('app.upload_failed')}');

  /// 插件的 `error`：成功恒为 int 0，失败是**给人看的字符串**（词典里的措辞，原样透出）。
  /// 字段缺失也算失败（fail-closed）：没拿到明确的 0 就不当成功。
  static void _throwIfPluginError(dynamic error) {
    if (error == 0) return;
    throw ApiException(-1, error is String && error.isNotEmpty ? error : '${AppTranslations.t('app.upload_failed')}');
  }
}
