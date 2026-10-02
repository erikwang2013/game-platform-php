// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:game_platform/app/i18n/locale_controller.dart';
import 'package:game_platform/app/i18n/translations.dart';

/// 13 语言文案表的常驻钉子。
///
/// **为什么需要**：`AppTranslations.get()` 的回落链是 `本语言 → en → 键名` —— 缺键
/// **不报错、不显眼**，只是静默显示英文。切换器平铺 13 项（`LocaleController.supported`），
/// 于是「菜单能选 13 种、实际只有 2 种真翻译」这种状态可以一直挂着没人发现：
/// 界面上完全看不出异常，只有比对两份表才知道（实测就是这么发生的）。
///
/// 三条断言各挡一种静默失败：
/// 1. **键集相等** —— 挡漏键（回落英文）与多键（拼错前缀的死键）。
/// 2. **占位符相等** —— 挡翻译时把 `{name}` / `%name%` 写进去：C 端是
///    `"${AppTranslations.t('k')}"` 直接拼字符串，**没有任何插值机制**，
///    译文里出现占位符只会被原样显示出来。基准表一个都没有，故 13 张表都必须为空。
/// 3. **非空值** —— 挡整行漏写 (`'k': ''`)。
void main() {
  final data = AppTranslations.data;
  final en = data['en']!;

  /// 与 `LocaleController.supported` 是**两份清单**（一份是界面菜单、一份是表）。
  /// 只有两边都齐，切过去才有真译文；对不上就是「菜单有这项、表里没有」。
  test('语言清单与 LocaleController.supported 完全一致（不多不少）', () {
    expect(
      data.keys.toSet(),
      LocaleController.supported.map((l) => l.code).toSet(),
      reason: 'supported 里有的语言表里没有 ⇒ 切过去全是英文回落；表里有而菜单没有 ⇒ 死表',
    );
    expect(data.length, 13);
  });

  test('13 种语言的键集两两相等（以 en 为基准）', () {
    final enKeys = en.keys.toSet();
    // 223 → 214（2026-10-02）：优惠券页撤下，`nav.coupons` + `coupon.*` 共 9 条随之清掉
    // （与两棵 web 树对齐 —— 它们撤页时也是连词条一起删的，不留孤儿键）。
    // 这是**有意为之**的键集变更，不是漏键；再改这个数请同样在 reason 里写明是哪一批。
    expect(enKeys.length, 214,
        reason: '基准表键数变了 ⇒ 下面每条都失去意义，先确认是有意为之');

    for (final entry in data.entries) {
      final keys = entry.value.keys.toSet();
      expect(
        keys.difference(enKeys),
        isEmpty,
        reason: '${entry.key}: 表里有 en 没有的键（拼错前缀的死键，永不显示）',
      );
      expect(
        enKeys.difference(keys),
        isEmpty,
        reason: '${entry.key}: 比 en 少键 ⇒ 这些键静默回落成英文，界面「看着有翻译」其实没有',
      );
    }
  });

  test('占位符逐键保持（13 张表都不能带占位符）', () {
    // C 端两条渲染路径都不插值：`t()` 返回的串被直接 `"${...}"` 拼进 Text。
    final braces = RegExp(r'\{[a-zA-Z_][a-zA-Z_0-9]*\}');
    final percent = RegExp(r'%[a-zA-Z_][a-zA-Z_0-9]*%');
    for (final entry in data.entries) {
      for (final kv in entry.value.entries) {
        final hit = [
          ...braces.allMatches(kv.value).map((m) => m.group(0)!),
          ...percent.allMatches(kv.value).map((m) => m.group(0)!),
        ];
        expect(hit, isEmpty,
            reason: '${entry.key} 的 ${kv.key} 带占位符 $hit ⇒ 本端无插值，会原样显示给用户');
      }
    }
    // 基准表本身也必须没有占位符，否则上面这条就是想当然（恒真）
    expect(braces.allMatches(en.values.join(' ')), isEmpty);
    expect(percent.allMatches(en.values.join(' ')), isEmpty);
  });

  test('没有空译文', () {
    for (final entry in data.entries) {
      final empty =
          entry.value.entries.where((e) => e.value.trim().isEmpty).map((e) => e.key);
      expect(empty, isEmpty, reason: '${entry.key} 有空值：${empty.take(5).toList()}');
    }
  });

  /// 假翻译烟雾报警：整表照抄英文（「机制接好了、表没翻」正是用户报的那个症状）。
  ///
  /// 阈值刻意压得很低 —— 它是**冒烟报警不是质量门**：专有名词（KYC/OAuth/PayPal/URL）、
  /// 纯符号、语言自名本来就该逐字照搬，真实的「与 en 相同」比例天然不低。
  test('每种语言确实翻了（与 en 逐字相同的值不过半）', () {
    for (final entry in data.entries) {
      if (entry.key == 'en' || entry.key == 'zh') continue;
      final total = en.length;
      final same = en.keys.where((k) => entry.value[k] == en[k]).length;
      expect(
        same * 2,
        lessThan(total),
        reason: '${entry.key} 有过半（$same/$total）的值与英文逐字相同 ⇒ 疑似整表照抄',
      );
    }
  });

  /// 用户报的原始症状：选「日本語」界面一个字都不变（全英文）。
  /// `t()` 走的是 `Get.locale.languageCode` 查表这条路 —— 表里没有 'ja' 时才回落英文。
  test('t() 按 Get.locale 查表：日本語 出日文，不是英文回落', () {
    final saved = Get.locale;
    try {
      Get.locale = const Locale('ja', 'JP');
      expect(AppTranslations.t('app.title').toString(), 'グローバルゲームプラットフォーム');
      expect(AppTranslations.t('nav.wallet').toString(), 'マイウォレット');
    } finally {
      Get.locale = saved;
    }
  });

  /// 只钉**回落链本身**：认不出的语言 → en；认不出的键 → 键名。
  /// 刻意不复用任何真表里的键 —— 否则它会和上一条「键集相等」重复报警，
  /// 一次删键变成两条红，就看不出是哪一层挡住的。
  test('回落链：认不出的语言走 en、认不出的键返回键名（不是抛异常、不是空串）', () {
    expect(AppTranslations.get('app.title', 'nope'), 'Global Game Platform');
    expect(AppTranslations.get('no.such.key', 'ja'), 'no.such.key');
    expect(AppTranslations.get('no.such.key', 'nope'), 'no.such.key');
  });
}
