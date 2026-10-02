// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'package:get/get.dart';

import 'locales/ar.dart';
import 'locales/bn.dart';
import 'locales/de.dart';
import 'locales/en.dart';
import 'locales/es.dart';
import 'locales/fr.dart';
import 'locales/hi.dart';
import 'locales/id.dart';
import 'locales/ja.dart';
import 'locales/ko.dart';
import 'locales/pt.dart';
import 'locales/ru.dart';
import 'locales/zh.dart';

/// 13 语言文案表。
///
/// 表体按语言拆到 `locales/<code>.dart`（单表 223 键，13 张挤在一个文件里会到 ~3600 行），
/// 这里只做汇总 —— **别把表体搬回来**。
///
/// `en` 是基准：`get()` 的回落链是 `本语言 → en → 键名`。加语言必须**先**在 `locales/`
/// 下建出同键集的表并加进 [_data]，顺序反了会静默回落成英文（看着「能用」，其实没翻）。
/// 键集两两相等由 `test/i18n_locales_test.dart` 钉着。
class AppTranslations {
  static const Map<String, Map<String, String>> _data = {
    'en': en,
    'zh': zh,
    'ja': ja,
    'ko': ko,
    'ru': ru,
    'de': de,
    'fr': fr,
    'es': es,
    'pt': pt,
    'hi': hi,
    'ar': ar,
    'bn': bn,
    'id': id,
  };

  /// 语言码 → 该语言的键值表 —— 给钉子用例遍历用（`test/i18n_locales_test.dart`）。
  ///
  /// 只读的对外投影，不复制：两处各存一份必然漂移。
  static Map<String, Map<String, String>> get data => _data;

  static StringResult t(String key) {
    final locale = Get.locale?.languageCode ?? 'en';
    return StringResult(key, locale);
  }

  static String get(String key, String locale) {
    return _data[locale]?[key] ?? _data['en']?[key] ?? key;
  }
}

class StringResult {
  final String _key;
  final String _locale;
  StringResult(this._key, this._locale);

  @override
  String toString() => AppTranslations.get(_key, _locale);
}
