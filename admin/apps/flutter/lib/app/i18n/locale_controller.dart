// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// 一个受支持的语言：短码 + 母语名 + Flutter 自己的 `Locale`。
///
/// [code] 是**短码**，两个用途：存进偏好、以及作为 `X-Language` 发给后端
/// （后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码两种写法）。
class AppLocale {
  const AppLocale(this.code, this.nativeName, this.locale);

  final String code;
  final String nativeName;
  final Locale locale;
}

class LocaleController extends GetxController {
  static const _localeKey = 'app_locale';

  /// 支持的语言 —— **镜像后端 `common\Locale::SUPPORTED`（13 种，与 `install/lang/*.php` 对齐）**。
  ///
  /// 为什么是本地镜像而不是从后端拉：这是**设置菜单**，离线/未登录/后端不可达时也要能渲染，
  /// 拉不到就整块菜单空掉是不能接受的。代价是这份清单成了第二处副本 —— 真值面在后端
  /// （`TranslationService::getAvailableLanguages()`，且有钉子断言它归一后 == `Locale::supported()`），
  /// **后端增删语言时这里必须同步**。
  ///
  /// 旧实现是 `['en','zh'].contains(saved)` + `saved == 'zh' ? Locale('zh','CN') : Locale('en','US')`
  /// 的三元写法：其余 11 种语言既存不进偏好、也切不过去 —— 后端早已支持 13 种，
  /// 界面却只能二选一。
  static const supported = <AppLocale>[
    AppLocale('en', 'English', Locale('en', 'US')),
    AppLocale('zh', '简体中文', Locale('zh', 'CN')),
    AppLocale('ja', '日本語', Locale('ja', 'JP')),
    AppLocale('ko', '한국어', Locale('ko', 'KR')),
    AppLocale('ru', 'Русский', Locale('ru', 'RU')),
    AppLocale('de', 'Deutsch', Locale('de', 'DE')),
    AppLocale('fr', 'Français', Locale('fr', 'FR')),
    AppLocale('es', 'Español', Locale('es', 'ES')),
    AppLocale('pt', 'Português', Locale('pt', 'PT')),
    AppLocale('hi', 'हिन्दी', Locale('hi', 'IN')),
    AppLocale('ar', 'العربية', Locale('ar', 'SA')),
    AppLocale('bn', 'বাংলা', Locale('bn', 'BD')),
    AppLocale('id', 'Bahasa Indonesia', Locale('id', 'ID')),
  ];

  /// 当前语言短码 —— **给 `ApiService` 的发请求拦截器读**（`X-Language` 头）。
  ///
  /// 用静态字段而不是让拦截器去 `Get.find<LocaleController>()`：拦截器在 Dio 里跑，
  /// 拿不到 Controller 时不该抛异常把请求打死；静态字段没有这个失败模式，也让拦截器不必依赖 Get。
  static String currentCode = 'en';

  /// 认不出来的码回落 `en`（旧实现直接把非 en/zh 的值丢掉、连偏好都不写）
  static AppLocale resolve(String? code) => supported.firstWhere(
        (item) => item.code == code,
        orElse: () => supported.first,
      );

  RxString currentLocale = 'en'.obs;

  @override
  void onInit() {
    super.onInit();
    _loadLocale();
  }

  Future<void> _loadLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_localeKey);
    if (saved != null) {
      _apply(saved);
    }
  }

  Future<void> changeLocale(String code) async {
    _apply(code);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeKey, code);
  }

  /// 落值的三处必须一起动：静态码（请求头）、响应式码（界面）、Get 的 Locale（翻译查表）。
  void _apply(String code) {
    final locale = resolve(code);
    currentCode = locale.code;
    currentLocale.value = locale.code;
    Get.updateLocale(locale.locale);
  }
}
