// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:admin_app/app/i18n/locale_controller.dart';
import 'package:admin_app/app/i18n/translations.dart';
import 'package:flutter_test/flutter_test.dart';

/// 13 语言文案表的常驻钉子。
///
/// **为什么需要**：`AppTranslations.get()` 的回落链是 `本语言 → en → 键名` —— 缺键
/// **不报错、不显眼**，只是静默显示英文。切换器平铺 13 项（`LocaleController.supported`），
/// 于是「菜单能选 13 种、实际只有 2 种真翻译」这种状态可以一直挂着没人发现：
/// 界面上完全看不出异常，只有比对两份表才知道（实测就是这么发生的）。
///
/// 三条断言各挡一种静默失败：
/// 1. **键集相等** —— 挡漏键（回落英文）与多键（拼错前缀的死键）。
/// 2. **占位符多重集相等** —— 挡翻译时把 `{name}` 写丢/写残：译文照样渲染，
///    只是该填的值永远不出现（`Delete "{name}"?` 变成 `要删除吗？`）。
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
    expect(enKeys.length, 668, reason: '基准表键数变了 ⇒ 下面每条都失去意义，先确认是有意为之');

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

  test('占位符逐键保持（{} 里的名字一字不改）', () {
    // 只认 `{标识符}`：JSON 例子里的 `{"type": ...}` 不匹配，不会误判。
    final re = RegExp(r'\{[a-zA-Z_][a-zA-Z_0-9]*\}');
    List<String> ph(String s) => (re.allMatches(s).map((m) => m.group(0)!).toList()..sort());

    for (final key in en.keys) {
      final expected = ph(en[key]!);
      if (expected.isEmpty) continue;
      for (final entry in data.entries) {
        if (entry.key == 'en') continue;
        expect(
          ph(entry.value[key] ?? ''),
          expected,
          reason: '${entry.key} 的 $key 占位符对不上：译文里丢了/多了参数 ⇒ 该填的值永远不出现',
        );
      }
    }
  });

  test('没有空译文', () {
    for (final entry in data.entries) {
      final empty = entry.value.entries.where((e) => e.value.trim().isEmpty).map((e) => e.key);
      expect(empty, isEmpty, reason: '${entry.key} 有空值：${empty.take(5).toList()}');
    }
  });

  /// 假翻译烟雾报警：整表照抄英文（「机制接好了、表没翻」正是用户报的那个症状）。
  ///
  /// 阈值刻意压得很低 —— 它是**冒烟报警不是质量门**：专有名词（hashid/CDN/KYC）、
  /// 纯符号、单位串本来就该逐字照搬，真实的「与 en 相同」比例天然不低。
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
}
