// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 批次 5（风控）七个页签的接线自检，重点钉本批的三类硬约束：
//   1) 整单替换的编辑（规则：update 复用 create 的 fill()）必须整份提交，缺 name/type/action 就 422；
//   2) 值域不是 0/1 的字段不许当开关用：团伙 status ∈ {0,1,2}、反作弊 status ∈ 字符串枚举；
//   3) 金额/哈希一律**字符串原样透传**（bcmath 串、64 位指纹），二次确认文案里回显原文。
//
// 网络一律用「记账假控制器」替掉（与 crud3/crud4 同法）：请求体形状（路径/动词）由控制器内部
// new ApiService，没有注入缝，这里不覆盖 —— 真值来自各控制器 validator（见各页文件头注释）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/risk/anticheat_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_cluster_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_device_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_event_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_ip_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_rule_manage_tab.dart';
import 'package:admin_app/app/pages/risk/risk_user_manage_tab.dart';
import 'test_helpers.dart';

class _FakeRuleCtrl extends RiskRuleManageController {
  final created = <Map<String, dynamic>>[];
  final updated = <(String, Map<String, dynamic>)>[];
  final toggled = <String>[];
  final tests = <Map<String, dynamic>>[];
  Map<String, dynamic> testData = <String, dynamic>{'matched': true, 'severity': 60, 'action': 'warn', 'message': 'hit'};

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<void> create(Map<String, dynamic> data) async => created.add(data);

  @override
  Future<void> updateRule(String hashid, Map<String, dynamic> data) async => updated.add((hashid, data));

  @override
  Future<void> toggle(String hashid) async => toggled.add(hashid);

  @override
  Future<Map<String, dynamic>> test(Map<String, dynamic> data) async {
    tests.add(data);
    return testData;
  }
}

class _FakeEventCtrl extends RiskEventManageController {
  final handled = <(String, String, String)>[];
  String message = 'server: recorded';

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<String> handle(String hashid, String decision, String note) async {
    handled.add((hashid, decision, note));
    return message;
  }
}

class _FakeDeviceCtrl extends RiskDeviceManageController {
  final calls = <(String, String)>[];

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<void> block(String fpHash) async => calls.add(('block', fpHash));

  @override
  Future<void> unblock(String fpHash) async => calls.add(('unblock', fpHash));
}

class _FakeIpCtrl extends RiskIpManageController {
  final calls = <(String, String)>[];
  String message = 'server: cache refreshed';

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<String> act(String action, String ip) async {
    calls.add((action, ip));
    return message;
  }
}

class _FakeUserCtrl extends RiskUserManageController {
  final holds = <String>[];
  final releases = <(String, String)>[];
  String frozen = '100.00000000';
  String released = '100.00000000';

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<String> hold(String hashid) async {
    holds.add(hashid);
    return frozen;
  }

  @override
  Future<String> release(String hashid, String amount) async {
    releases.add((hashid, amount));
    return released;
  }
}

class _FakeClusterCtrl extends RiskClusterManageController {
  final confirmed = <Map<String, dynamic>>[];
  final statuses = <(String, int)>[];

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<void> detect() async {
    candidates.value = <dynamic>[
      <String, dynamic>{
        'type': 'same_ip',
        'fingerprint': 'deadbeef00112233',
        'fingerprint_masked': 'deadbeef****',
        'user_count': 6,
      },
    ];
    windowDays.value = 7;
  }

  @override
  Future<void> confirm(Map<String, dynamic> data) async => confirmed.add(data);

  @override
  Future<void> setStatus(String hashid, int status) async => statuses.add((hashid, status));
}

class _FakeAcCtrl extends AntiCheatManageController {
  final reviews = <(String, String, String)>[];

  @override
  Future<void> load({int? toPage}) async {}

  @override
  Future<String> review(String hashid, String status, String note) async {
    reviews.add((hashid, status, note));
    return 'server: review recorded';
  }
}

Map<String, dynamic> ruleRow() => <String, dynamic>{
      'id': 'r-1',
      'name': 'Frequency',
      'type': 'frequency',
      'action': 'warn',
      'scope': 'all',
      'config': '{"window_minutes":10}',
      'priority': 10,
      'status': 1,
    };

/// 设备行的完整指纹（列表新契约回传的原文）：动作提交它，展示/确认用它的前 8 位掩码。
const String _fullFp = 'deadbeef0123456789abcdef0123456789abcdef0123456789abcdef01234567';

Map<String, dynamic> deviceRow() => <String, dynamic>{
      'fp_hash': _fullFp,
      'fp_masked': 'deadbeef****', // = fp_hash 前 8 位 + ****，与服务端 substr 口径一致
      'ip_c_segment': '203.0.113',
      'account_count': 6,
      'first_seen_at': '2026-01-01 00:00:00',
      'last_seen_at': '2026-01-02 00:00:00',
      'blocked': false,
    };

Map<String, dynamic> eventRow() => <String, dynamic>{
      'id': 'e-1',
      'type': 'frequency',
      'action': 'warn',
      'result': 'hit',
      'rule_name': 'Frequency',
      'user_id': 'u-1',
      'created_at': '2026-01-01 10:00:00',
    };

void main() {
  setUp(setUpTest);

  Future<void> pumpPage(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(1500, 1100);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(locale: const Locale('en', 'US'), home: Scaffold(body: page)));
    await tester.pumpAndSettle();
  }

  /// snackbar 自带自动关闭 Timer，测试结束前必须让它到期（否则报 pending timer）；
  /// 且它悬在顶部满宽，会挡住后续点击 ⇒ 动界面前先冲干净。
  Future<void> flushSnackbars(WidgetTester tester) async {
    for (var i = 0; i < 2; i++) {
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
    }
  }

  Finder rowIcon(IconData icon) =>
      find.descendant(of: find.byType(DataTable), matching: find.byIcon(icon));

  Future<void> pickOption(WidgetTester tester, String option) async {
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text(option).last);
    await tester.pumpAndSettle();
  }

  group('规则', () {
    testWidgets('编辑整份提交：name/type/action 一个不落（PUT 复用 create 的 fill()）', (tester) async {
      final ctrl = _FakeRuleCtrl()..items.value = <dynamic>[ruleRow()];
      Get.put<RiskRuleManageController>(ctrl);
      await pumpPage(tester, const RiskRuleManageTab());

      await tester.tap(rowIcon(Icons.edit));
      await tester.pumpAndSettle();
      expect(find.text('Edit Rule'), findsOneWidget);

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(ctrl.updated.single.$1, 'r-1');
      expect(
        ctrl.updated.single.$2.keys.toSet(),
        <String>{'name', 'type', 'action', 'scope', 'config', 'priority', 'status'},
      );
      expect(ctrl.updated.single.$2['type'], 'frequency'); // 值域内的原值照发，不被顶成第一项
      await flushSnackbars(tester);
    });

    testWidgets('启停无 body：服务端自己翻转，客户端不传目标状态', (tester) async {
      final ctrl = _FakeRuleCtrl()..items.value = <dynamic>[ruleRow()];
      Get.put<RiskRuleManageController>(ctrl);
      await pumpPage(tester, const RiskRuleManageTab());

      await tester.tap(find.descendant(of: find.byType(DataTable), matching: find.byType(Switch)));
      await tester.pumpAndSettle();

      expect(ctrl.toggled, <String>['r-1']);
    });

    testWidgets('沙箱试算：context 必须是 JSON 对象（字符串会被服务端静默兜成 []）', (tester) async {
      final ctrl = _FakeRuleCtrl()..items.value = <dynamic>[ruleRow()];
      Get.put<RiskRuleManageController>(ctrl);
      await pumpPage(tester, const RiskRuleManageTab());

      await tester.tap(rowIcon(Icons.play_circle_outline));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'User'), 'u-1');
      await tester.enterText(find.widgetWithText(TextField, 'Context (JSON object)'), 'nope');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(ctrl.tests, isEmpty); // 本地拦下，不发出去
      expect(find.text('Context must be a JSON object, e.g. {"ip":"1.2.3.4"}'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Context (JSON object)'), '{"ip":"1.2.3.4"}');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(ctrl.tests.single['rule_id'], 'r-1');
      expect(ctrl.tests.single['context'], <String, dynamic>{'ip': '1.2.3.4'}); // 对象，不是字符串
      expect(find.textContaining('matched=true'), findsOneWidget);

      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
      await flushSnackbars(tester);
    });
  });

  group('事件', () {
    testWidgets('驳回要二次确认（文案带规则名与命中时间），取消不发，通过直接发', (tester) async {
      final ctrl = _FakeEventCtrl()..items.value = <dynamic>[eventRow()];
      Get.put<RiskEventManageController>(ctrl);
      await pumpPage(tester, const RiskEventManageTab());

      // 默认结论是 select 的第一项 approve ⇒ 直接发
      await tester.tap(rowIcon(Icons.gavel_outlined));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(ctrl.handled.single, ('e-1', 'approve', ''));
      await flushSnackbars(tester);

      // 选 reject ⇒ 出确认框；先取消
      await tester.tap(rowIcon(Icons.gavel_outlined));
      await tester.pumpAndSettle();
      await pickOption(tester, 'Reject');
      await tester.tap(find.text('Save'));
      // 确认框叠在「提交中」的表单上（Save 已是无限转圈）⇒ 不能 pumpAndSettle
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.textContaining('"Frequency @ 2026-01-01 10:00:00"'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.handled.length, 1); // 一个字节都没发

      // 再来一次，这回确认到底
      await tester.tap(rowIcon(Icons.gavel_outlined));
      await tester.pumpAndSettle();
      await pickOption(tester, 'Reject');
      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Handle'));
      await tester.pumpAndSettle();

      expect(ctrl.handled.last, ('e-1', 'reject', ''));
      expect(find.text('server: recorded'), findsOneWidget); // 成功文案 = 服务端 message
      await flushSnackbars(tester);
    });
  });

  group('设备', () {
    testWidgets('行内拉黑：请求体带**行里的完整** fp_hash，确认文案认行用 fp_masked', (tester) async {
      final ctrl = _FakeDeviceCtrl()..items.value = <dynamic>[deviceRow()];
      Get.put<RiskDeviceManageController>(ctrl);
      await pumpPage(tester, const RiskDeviceManageTab());

      // 列表现在每行同时回 fp_hash（动作入参）与 fp_masked（展示）⇒ 不需要页级手填表单
      expect(find.widgetWithText(TextField, 'Device fingerprint (full 64-hex)'), findsNothing);

      await tester.tap(rowIcon(Icons.block));
      await tester.pumpAndSettle();
      // 确认框里是**掩码**：行是按掩码认的，露 64 位十六进制没人核对得了
      expect(find.descendant(of: find.byType(AlertDialog).last, matching: find.textContaining('deadbeef****')), findsOneWidget);
      expect(find.descendant(of: find.byType(AlertDialog).last, matching: find.textContaining(_fullFp)), findsNothing);

      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pumpAndSettle();
      expect(ctrl.calls, isEmpty); // 取消 ⇒ 一个字节都不发
      await flushSnackbars(tester);

      await tester.tap(rowIcon(Icons.block));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ElevatedButton, 'Block device'));
      await tester.pumpAndSettle();

      expect(ctrl.calls.single, ('block', _fullFp)); // 提交的是**完整**哈希（掩码提交不上去）
      await flushSnackbars(tester);
    });

    testWidgets('已拉黑的行只能解封：动作由行自己的 blocked 决定，不是对称开关', (tester) async {
      final ctrl = _FakeDeviceCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{...deviceRow(), 'blocked': true},
        ];
      Get.put<RiskDeviceManageController>(ctrl);
      await pumpPage(tester, const RiskDeviceManageTab());

      expect(find.text('Yes'), findsOneWidget); // blocked 列读的是服务端短路后的标记
      expect(rowIcon(Icons.block), findsNothing);
      await tester.tap(rowIcon(Icons.lock_open));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ElevatedButton, 'Unblock device'));
      await tester.pumpAndSettle();

      expect(ctrl.calls.single, ('unblock', _fullFp));
      await flushSnackbars(tester);
    });
  });

  group('IP 信誉', () {
    testWidgets('重查不问（只清缓存）且读服务端 data.message', (tester) async {
      final ctrl = _FakeIpCtrl();
      Get.put<RiskIpManageController>(ctrl);
      await pumpPage(tester, const RiskIpManageTab());

      await tester.tap(find.text('Recheck'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'IP address'), '203.0.113.7');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsNothing); // 不是危险动作，不弹确认
      expect(ctrl.calls.single, ('recheck', '203.0.113.7'));
      expect(find.text('server: cache refreshed'), findsOneWidget);
      await flushSnackbars(tester);
    });

    testWidgets('拉黑要二次确认（文案带明文 IP），取消不发', (tester) async {
      final ctrl = _FakeIpCtrl();
      Get.put<RiskIpManageController>(ctrl);
      await pumpPage(tester, const RiskIpManageTab());

      await tester.tap(find.text('Block IP'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'IP address'), '203.0.113.7');
      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      // 同上：输入框里那份也含这个 IP，限到确认框才钉得住「确认文案带明文 IP」
      expect(find.descendant(of: find.byType(AlertDialog).last, matching: find.textContaining('203.0.113.7')), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.calls, isEmpty);
    });
  });

  group('异常用户', () {
    testWidgets('冻结：确认文案带用户名，金额读服务端 frozen_amount 原样回显', (tester) async {
      final ctrl = _FakeUserCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{'user_id': 'u-1', 'username': 'zhangsan', 'score': 20, 'band': 'observe', 'hit_count': 3, 'last_hit_at': '2026-01-01 00:00:00', 'whitelisted': 0},
        ];
      Get.put<RiskUserManageController>(ctrl);
      await pumpPage(tester, const RiskUserManageTab());

      expect(find.text('Observe'), findsOneWidget); // band 走 crudEnum（值域外的值原样显示）

      await tester.tap(rowIcon(Icons.ac_unit));
      await tester.pumpAndSettle();
      expect(find.textContaining('"zhangsan"'), findsOneWidget);

      await tester.tap(find.widgetWithText(ElevatedButton, 'Freeze'));
      await tester.pumpAndSettle();

      expect(ctrl.holds, <String>['u-1']);
      expect(find.text('Frozen 100.00000000 platform tokens'), findsOneWidget); // 服务端原值，不经 double
      await flushSnackbars(tester);
    });

    testWidgets('解冻：金额是 text 控件、原样上送，留空 = 全额', (tester) async {
      final ctrl = _FakeUserCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{'user_id': 'u-1', 'username': 'zhangsan', 'score': 20, 'band': 'normal', 'hit_count': 0, 'last_hit_at': '', 'whitelisted': 0},
        ];
      Get.put<RiskUserManageController>(ctrl);
      await pumpPage(tester, const RiskUserManageTab());

      // 正控：金额字段必须**不是**数字键盘（TextField 不给 keyboardType 时默认是 TextInputType.text，
      // 不是 null ⇒ 只能反着断言；带 decimal 的数字键盘会把 '0.1' 变成 0.1000000000000000055）
      await tester.tap(rowIcon(Icons.lock_open));
      await tester.pumpAndSettle();
      final amount = tester.widget<TextField>(find.widgetWithText(TextField, 'Amount'));
      expect(amount.keyboardType.decimal, isNull);
      expect(amount.keyboardType, isNot(TextInputType.number));

      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      // 限到确认框：表单里那句同义的 hint 也在树上，不限就分不清「范围写进确认文案了没有」
      expect(find.descendant(of: find.byType(AlertDialog).last, matching: find.textContaining('everything frozen')), findsOneWidget); // 留空 ⇒ 文案要说明释放范围
      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.releases, isEmpty);

      await tester.tap(rowIcon(Icons.lock_open));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'Amount'), '0.1');
      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.descendant(of: find.byType(AlertDialog).last, matching: find.textContaining('0.1')), findsOneWidget); // 确认文案里是原串，不是 0.1000…
      await tester.tap(find.widgetWithText(ElevatedButton, 'Unfreeze'));
      await tester.pumpAndSettle();

      expect(ctrl.releases.single, ('u-1', '0.1')); // 字符串，原样
      expect(find.text('Released 100.00000000'), findsOneWidget);
      await flushSnackbars(tester);
    });
  });

  group('团伙', () {
    testWidgets('检测候选不落库：确认为团伙时用候选原文预填，且不发 member_ids', (tester) async {
      final ctrl = _FakeClusterCtrl();
      Get.put<RiskClusterManageController>(ctrl);
      await pumpPage(tester, const RiskClusterManageTab());

      await tester.tap(find.text('Detect candidates'));
      await tester.pumpAndSettle();
      expect(find.text('Candidates (last 7 days, not persisted)'), findsOneWidget);
      expect(find.textContaining('deadbeef****'), findsOneWidget);

      await tester.tap(find.text('Confirm'));
      await tester.pumpAndSettle();
      expect(find.text('Confirm cluster'), findsOneWidget);
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Fingerprint')).controller?.text, 'deadbeef00112233');
      // 候选面板上刚显示过「6 个账户」，确认后落库要是 0 就是当场丢数据（列表有这一列）
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Members')).controller?.text, '6');

      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      expect(ctrl.confirmed.single['type'], 'same_ip');
      expect(ctrl.confirmed.single['fingerprint'], 'deadbeef00112233');
      expect(ctrl.confirmed.single['user_count'], 6);
      // member_ids 现在收 hashid（服务端逐个 decodeId），但建团这一刻手里没有 hashid：
      // 候选只带指纹，成员 hashid 只对已落库的团伙从 members 端点取 ⇒ 走指纹回填、不上送该字段
      expect(ctrl.confirmed.single.containsKey('member_ids'), isFalse);
      await flushSnackbars(tester);
    });

    testWidgets('状态是三值不是开关：菜单里发 2（已处置）', (tester) async {
      final ctrl = _FakeClusterCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{'id': 'c-1', 'name': 'Ring A', 'type': 'same_ip', 'fingerprint': 'deadbeef00112233', 'fingerprint_masked': 'deadbeef****', 'user_count': 6, 'status': 1, 'created_at': '', 'updated_at': ''},
        ];
      Get.put<RiskClusterManageController>(ctrl);
      await pumpPage(tester, const RiskClusterManageTab());

      expect(find.text('Watching'), findsOneWidget); // 1 = 观察中
      await tester.tap(find.byType(PopupMenuButton<int>));
      await tester.pumpAndSettle();
      // 点菜单项本体、不点文字：CheckedPopupMenuItem 内部套了一层 IgnorePointer（SDK 自带），
      // 文字永远过不了 flutter_test 的命中判定，只会得到一条「would not hit test」警告
      await tester.tap(find.widgetWithText(CheckedPopupMenuItem<int>, 'Handled'));
      await tester.pumpAndSettle();

      expect(ctrl.statuses.single, ('c-1', 2));
    });
  });

  group('反作弊', () {
    testWidgets('加白要二次确认，confirmed 直接发；status 是字符串枚举', (tester) async {
      final ctrl = _FakeAcCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{'id': 'ac-1', 'rule_name': 'Auto click', 'rule_type': 'auto_click', 'user_id': 'u-1', 'severity': 3, 'score_delta': -10, 'action': 'warn', 'evidence': <String, dynamic>{'round_id': 'rd-1'}, 'status': 'open', 'review_note': '', 'created_at': '2026-01-01 00:00:00'},
        ];
      Get.put<AntiCheatManageController>(ctrl);
      await pumpPage(tester, const AntiCheatManageTab());

      // confirmed：不额外确认，直接记录
      await tester.tap(rowIcon(Icons.rate_review_outlined));
      await tester.pumpAndSettle();
      await pickOption(tester, 'Confirmed');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      expect(ctrl.reviews.single, ('ac-1', 'confirmed', ''));
      await flushSnackbars(tester);

      // whitelisted：否定指控 ⇒ 先问一句；取消不发
      await tester.tap(rowIcon(Icons.rate_review_outlined));
      await tester.pumpAndSettle();
      await pickOption(tester, 'Whitelisted');
      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.textContaining('Auto click @ 2026-01-01 00:00:00'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.reviews.length, 1);
    });

    testWidgets('证据原文不给看就只能猜：对象逐行展开', (tester) async {
      final ctrl = _FakeAcCtrl()
        ..items.value = <dynamic>[
          <String, dynamic>{'id': 'ac-1', 'rule_name': 'Auto click', 'rule_type': 'auto_click', 'user_id': 'u-1', 'severity': 3, 'score_delta': -10, 'action': 'warn', 'evidence': <String, dynamic>{'round_id': 'rd-1', 'clicks': 42}, 'status': 'open', 'review_note': '', 'created_at': '2026-01-01 00:00:00'},
        ];
      Get.put<AntiCheatManageController>(ctrl);
      await pumpPage(tester, const AntiCheatManageTab());

      await tester.tap(rowIcon(Icons.article_outlined));
      await tester.pumpAndSettle();

      expect(find.textContaining('round_id: rd-1'), findsOneWidget);
      expect(find.textContaining('clicks: 42'), findsOneWidget);
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
    });
  });
}
