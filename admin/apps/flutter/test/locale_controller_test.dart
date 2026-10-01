// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:admin_app/app/i18n/locale_controller.dart';

/// 语言列表从「en/zh 二选一」扩到 13 种（与后端 `common\Locale::SUPPORTED` 对齐）。
///
/// 为什么要钉：旧实现写死 `['en','zh'].contains(saved)`，其余 11 种**连存都存不进去**，
/// 界面上更是切不过去——而同一个字段 `currentCode` 还要作为 `X-Language` 发给后端。
/// 三处（偏好、界面、请求头）任意一处不同步，症状都是「界面切了但服务端消息还是旧语言」。
///
/// 与 C 端 `apps/flutter/platform/test/locale_controller_test.dart` 同构（两棵树同形）。
void main() {
  setUp(() {
    // 本文件用普通 `test()`（不起 binding）：`Get.updateLocale` 在没有 binding 时是 no-op，
    // 正好——这几条要验的是**控制器自己的三处同步**，不是 Get 的重建。
    // ⚠ 别为了「更真」改成 testWidgets：那样 `Get.updateLocale` 会触发 performReassemble，
    // 而 binding 断言 `inTest`（实测抛 `'inTest': is not true`）。
    Get.testMode = true;
  });

  test('13 种语言都在，且认得出非 en/zh 的短码', () {
    expect(LocaleController.supported.length, 13);

    // 挑两个旧白名单外的（含一个非拉丁字母的），正是要钉住那次扩展
    expect(LocaleController.resolve('ja').nativeName, '日本語');
    expect(LocaleController.resolve('bn').nativeName, 'বাংলা');
    // 母语名而不是译名：语言菜单的可用性前提就是「用户还看不懂当前界面语言时也能选对」
    expect(LocaleController.resolve('ar').locale, const Locale('ar', 'SA'));
  });

  test('认不出来的码回落 en，而不是把值原样透传下去', () {
    expect(LocaleController.resolve('klingon').code, 'en');
    expect(LocaleController.resolve(null).code, 'en');
  });

  test('切换语言：界面码、请求头码、落盘三处必须一致', () async {
    SharedPreferences.setMockInitialValues({});
    final controller = LocaleController();

    await controller.changeLocale('ja');

    expect(controller.currentLocale.value, 'ja', reason: '界面响应式码没跟上');
    expect(
      LocaleController.currentCode,
      'ja',
      reason: 'currentCode 是 ApiService 拼 X-Language 读的那个字段；它不同步就会出现'
          '「界面切成日语、请求头还是旧语言」',
    );

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('app_locale'), 'ja', reason: '没落盘 ⇒ 重启后语言丢失');
  });

  test('非法码落到 en 时，三处也一起落到 en（不留半截状态）', () async {
    SharedPreferences.setMockInitialValues({});
    final controller = LocaleController();

    await controller.changeLocale('klingon');

    expect(controller.currentLocale.value, 'en');
    expect(LocaleController.currentCode, 'en');
  });

  /// **成败点：语言有没有真的挂到出站请求上。**
  ///
  /// 上面几条只证明「控制器内部三处一致」，还差最后一跳——`X-Language` 必须出现在
  /// ApiService 实际发出的请求里，否则后端 `LanguageMiddleware` 一律按默认 zh 选表，
  /// 「界面切了日语、服务端 message 还是中文」。
  ///
  /// **这是源码级断言，不是行为级**（如实标注）：真跑一次 `dio.get` 需要 token，
  /// 而 `AuthService` 在测试环境走 `flutter_secure_storage` 的平台通道；叠加
  /// `Get.updateLocale` 的重建断言，端到端那条在本环境里打不通（C 端实测：超时 / `'inTest'` 断言）。
  /// 行为级的半边由后端侧的真请求覆盖（`X-Language: ja-JP` → 日语信封，已实测）。
  /// 写法同 PHP 侧 `GameSettlePayoutGuardTest`（读源码钉调用点，注释里写明是哪种证据）。
  test('ApiService 把 X-Language 挂在出站请求上（源码级）', () {
    final source = File('lib/app/services/api_service.dart').readAsStringSync();

    expect(
      source.contains("options.headers['X-Language'] = LocaleController.currentCode"),
      isTrue,
      reason: '拦截器里没有把当前语言挂上去 ⇒ 后端永远按默认 zh 回消息',
    );
    // 挂的是**控制器那个字段**而不是字面量：写死 'zh'/'en' 会让菜单切了语言而请求头不动
    expect(
      source.contains('LocaleController.currentCode'),
      isTrue,
      reason: 'X-Language 必须取自 LocaleController.currentCode，不能是写死的串',
    );
  });
}
