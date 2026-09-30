// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 批次 4（资金）五个页面的接线自检，重点钉本批的两条硬性要求：
//   1) 金额字段一律 text 控件（浮点控件会吃掉 DECIMAL 小数位）⇒ 断言 keyboardType 为空，
//      并用 number 字段（total_qty / sort）做正控，证明这条断言真能分辨两种控件；
//   2) 资金动作（审核/二次确认/批量/执行打款/同步）必须二次确认、文案带订单号与金额，
//      结果读服务端 message，**不做乐观更新** ⇒ 确认前不写后端 + 服务端拒绝时界面原地不动。
//
// 打网络的部分一律用「记账假控制器」替掉（与 crud3_pages_test.dart 同法）：
// 请求体形状（路径/动词）不在这里覆盖 —— ApiService 由控制器内部 new，没有注入缝，
// 断言它就得改生产代码。真值来自各控制器 validator（见各页文件头注释）。
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:admin_app/app/pages/cdn/cdn_page.dart';
import 'package:admin_app/app/pages/coupon/coupon_page.dart';
import 'package:admin_app/app/pages/payment/payment_page.dart';
import 'package:admin_app/app/pages/withdraw/withdraw_limits_tab.dart';
import 'package:admin_app/app/pages/withdraw/withdraw_page.dart';
import 'test_helpers.dart';

/// 提现页：所有写操作只记账，返回可指定的服务端 message（用真实节点会打网络）。
class _FakeWithdrawController extends WithdrawController {
  final reviewCalls = <List<String>>[];
  final batchCalls = <List<dynamic>>[];
  final payoutCalls = <String>[];
  final syncCalls = <String>[];
  String reviewMessage = 'server: reviewed';
  String payoutMessage = 'server: payout ok';
  Map<String, dynamic> syncData = <String, dynamic>{};

  @override
  Future<void> loadOrders() async {}

  @override
  Future<void> loadSwitch() async {}

  @override
  Future<String> review(String orderId, String action, String note) async {
    reviewCalls.add(<String>[orderId, action, note]);
    return reviewMessage;
  }

  @override
  Future<String> batchReview(List<String> ids, String action, String note) async {
    batchCalls.add(<dynamic>[ids, action, note]);
    return 'server: batch done';
  }

  @override
  Future<String> executePayout(String orderId) async {
    payoutCalls.add(orderId);
    return payoutMessage;
  }

  @override
  Future<Map<String, dynamic>> syncPayout(String orderId) async {
    syncCalls.add(orderId);
    return syncData;
  }
}

class _FakeLimitController extends WithdrawLimitController {
  final saved = <(String, Map<String, dynamic>)>[];
  final resets = <Map<String, dynamic>>[];

  @override
  Future<void> load() async {}

  @override
  Future<String> saveLimit(String hashid, Map<String, dynamic> data) async {
    saved.add((hashid, data));
    return 'ok';
  }

  @override
  Future<String> resetAll(Map<String, dynamic> data) async {
    resets.add(data);
    return 'server: reset ok';
  }
}

class _FakePaymentController extends PaymentController {
  final created = <Map<String, dynamic>>[];
  final toggled = <(String, int)>[];

  @override
  Future<void> load() async {}

  @override
  Future<void> create(Map<String, dynamic> data) async => created.add(data);

  @override
  Future<void> toggle(String hashid, int status) async => toggled.add((hashid, status));
}

class _FakeCdnController extends CdnController {
  final removed = <String>[];
  String testMessage = 'server: cdn probe ok';

  @override
  Future<void> load() async {}

  @override
  Future<String> test(String hashid) async => testMessage;

  @override
  Future<void> remove(String hashid) async => removed.add(hashid);
}

class _FakeCouponController extends CouponAdminController {
  final created = <Map<String, dynamic>>[];
  final statusCalls = <(String, int)>[];
  final removed = <String>[];
  Map<String, dynamic> statsData = <String, dynamic>{};

  @override
  Future<void> load() async {}

  @override
  Future<void> create(Map<String, dynamic> data) async => created.add(data);

  @override
  Future<void> setStatus(String hashid, int status) async => statusCalls.add((hashid, status));

  @override
  Future<void> remove(String hashid) async => removed.add(hashid);

  @override
  Future<Map<String, dynamic>> stats(String hashid) async => statsData;
}

/// 一行提现订单。默认 pending 且没人审过（reviewer_id=0）。
Map<String, dynamic> withdrawOrder({
  String id = 'w-1',
  String no = 'W20260101',
  String amount = '100.0000',
  String fiat = '100.00',
  String currency = 'USD',
  String status = 'pending',
  int reviewerId = 0,
  String payoutStatus = '',
}) =>
    <String, dynamic>{
      'id': id,
      'order_no': no,
      'platform_amount': amount,
      'fiat_amount': fiat,
      'currency': currency,
      'status': status,
      'reviewer_id': reviewerId,
      'payout_status': payoutStatus,
      'review_note': '',
      'created_at': '2026-01-01 00:00:00',
      'user': <String, dynamic>{'username': 'zhangsan'},
    };

void main() {
  setUp(setUpTest);

  /// 页面本身不带 Scaffold（它们在 AdminLayout 的 Scaffold body 里跑），测试要自己补上 Material 祖先。
  Future<void> pumpPage(WidgetTester tester, Widget page) async {
    tester.view.physicalSize = const Size(1400, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(GetMaterialApp(locale: const Locale('en', 'US'), home: Scaffold(body: page)));
    await tester.pumpAndSettle();
  }

  /// 加载失败/操作失败都会走 Get.snackbar（自带自动关闭 Timer）：测试结束前必须让它到期
  /// （否则 flutter_test 报「A Timer is still pending」），点了两次的还要等排队的那条上屏又下屏。
  /// snackbar 悬在顶部且满宽 ⇒ 会影响后续点击，动界面前必须先冲干净。
  Future<void> flushSnackbars(WidgetTester tester) async {
    for (var i = 0; i < 2; i++) {
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
    }
  }

  /// 行内动作按钮：表头 SegmentedButton 的选中段也带 Icons.check ⇒ 一律限定在表格里找。
  Finder rowIcon(IconData icon) =>
      find.descendant(of: find.byType(DataTable), matching: find.byIcon(icon));

  /// 表格里的行复选框（showCheckboxColumn 的列，可能还有表头那一个 ⇒ 取最后 N 个）。
  Finder rowCheckboxes() =>
      find.descendant(of: find.byType(DataTable), matching: find.byType(Checkbox));

  group('提现订单', () {
    testWidgets('行内动作由状态机决定：不摆点了必然 422 的按钮', (tester) async {
      final ctrl = _FakeWithdrawController();
      ctrl.orders.value = <dynamic>[
        withdrawOrder(), // pending + 无人审 ⇒ 通过 / 驳回
        withdrawOrder(id: 'w-2', no: 'W20260102', status: 'pending', reviewerId: 7), // ⇒ 二次确认 / 驳回
        withdrawOrder(id: 'w-3', no: 'W20260103', status: 'approved', reviewerId: 7), // ⇒ 执行打款
        withdrawOrder(id: 'w-4', no: 'W20260104', status: 'completed', payoutStatus: 'success'), // ⇒ 同步
      ];
      Get.put<WithdrawController>(ctrl);
      await pumpPage(tester, const WithdrawPage());

      expect(rowIcon(Icons.check), findsOneWidget); // 只有 reviewer_id=0 的那行能初审
      expect(rowIcon(Icons.verified), findsOneWidget); // 只有已有人审过的行能二次确认
      expect(rowIcon(Icons.close), findsNWidgets(2)); // 两行 pending 都能驳回
      expect(rowIcon(Icons.payments_outlined), findsOneWidget); // 只有 approved 能执行打款
      expect(rowIcon(Icons.sync), findsOneWidget); // 只有带批次号（payout_status 非空）能同步
      // 状态显示词（限定在表格里：状态筛选段与它们同名）
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Pending')), findsNWidgets(2));
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Approved')), findsOneWidget);
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Completed')), findsOneWidget);
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Paid')), findsOneWidget);
    });

    testWidgets('单笔审核：确认文案带订单号与金额，取消不写后端，确认后读服务端 message', (tester) async {
      final ctrl = _FakeWithdrawController();
      ctrl.orders.value = <dynamic>[withdrawOrder()];
      Get.put<WithdrawController>(ctrl);
      await pumpPage(tester, const WithdrawPage());

      Future<void> tapApproveAndSave() async {
        await tester.tap(rowIcon(Icons.check));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Save'));
        // 确认框叠在「提交中」的表单上（Save 已换成无限转圈的 spinner）⇒ 这里不能用 pumpAndSettle
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 400));
      }

      await tapApproveAndSave();
      expect(find.text('Review withdrawal: W20260101'), findsOneWidget); // 表单标题先带订单号
      expect(
        find.text('Approve withdrawal "W20260101" (100.0000 platform tokens (payout 100.00 USD))? '
            'Once approved it becomes payable.'),
        findsOneWidget,
      );

      // 取消 ⇒ 一个字节都不发
      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.reviewCalls, isEmpty);

      // 再来一次，这回确认到底（取消后表单也关了：底座把「onSubmit 没抛异常」一律当成提交完成）
      await tapApproveAndSave();
      await tester.tap(find.widgetWithText(ElevatedButton, 'Approve'));
      await tester.pumpAndSettle();

      expect(ctrl.reviewCalls, <List<String>>[
        <String>['w-1', 'approve', ''],
      ]);
      expect(find.text('server: reviewed'), findsOneWidget); // 成功文案 = 服务端 message，不是前端话术

      await flushSnackbars(tester);
    });

    testWidgets('批量审核：勾选行后按钮才可用，确认文案带合计与订单号清单', (tester) async {
      final ctrl = _FakeWithdrawController();
      ctrl.orders.value = <dynamic>[
        withdrawOrder(amount: '100.0000'),
        withdrawOrder(id: 'w-2', no: 'W20260102', amount: '0.1'), // 尾数不足 4 位：合计要补零
      ];
      Get.put<WithdrawController>(ctrl);
      await pumpPage(tester, const WithdrawPage());

      expect(find.text('0 selected'), findsOneWidget);
      expect(tester.widget<OutlinedButton>(find.widgetWithText(OutlinedButton, 'Batch Approve')).onPressed, isNull);

      final boxes = rowCheckboxes();
      final n = boxes.evaluate().length;
      await tester.tap(boxes.at(n - 1));
      await tester.pump();
      await tester.tap(boxes.at(n - 2));
      await tester.pump();
      expect(find.text('2 selected'), findsOneWidget);

      await tester.tap(find.widgetWithText(OutlinedButton, 'Batch Approve'));
      await tester.pumpAndSettle();
      expect(find.text('Batch review: 2 orders'), findsOneWidget); // 批量表单不带 confirm 动作选项
      expect(find.text('Second confirm'), findsNothing);

      await tester.tap(find.text('Save'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.text('Approve 2 withdrawal orders in one batch, total 100.1000?\nw-1, w-2'), findsOneWidget);

      await tester.tap(find.widgetWithText(ElevatedButton, 'Approve'));
      await tester.pumpAndSettle();
      expect(ctrl.batchCalls.single, <dynamic>[
        <String>['w-1', 'w-2'],
        'approve',
        '',
      ]);
      expect(find.text('server: batch done'), findsOneWidget);

      await flushSnackbars(tester);
    });

    testWidgets('打款同步：确认文案带订单号，结果读 data 里的三个状态', (tester) async {
      final ctrl = _FakeWithdrawController()
        ..syncData = <String, dynamic>{
          'payout_status': 'success',
          'order_status': 'completed',
          'synced_status': 'REFUND-1',
        };
      ctrl.orders.value = <dynamic>[withdrawOrder(status: 'completed', payoutStatus: 'failed')];
      Get.put<WithdrawController>(ctrl);
      await pumpPage(tester, const WithdrawPage());

      await tester.tap(rowIcon(Icons.sync));
      await tester.pumpAndSettle();
      expect(
        find.text('Sync the payout status of "W20260101" '
            '(100.0000 platform tokens (payout 100.00 USD)) from PayPal?'),
        findsOneWidget,
      );

      await tester.tap(find.widgetWithText(ElevatedButton, 'Sync payout'));
      await tester.pumpAndSettle();
      expect(ctrl.syncCalls, <String>['w-1']);
      expect(find.text('Payout status success, order status completed (PayPal batch REFUND-1)'), findsOneWidget);

      await flushSnackbars(tester);
    });

    testWidgets('服务端拒绝时不做乐观更新：开关不回弹、订单状态原地不动', (tester) async {
      // 这一条用**真实控制器**：flutter_test 的离线环境让请求全部失败 ＝「服务端拒绝」。
      // 乐观实现会在点上的一瞬间就把开关翻过去、把行状态改掉 —— 这里正是要证明它没有。
      Get.put<WithdrawController>(WithdrawController());
      await pumpPage(tester, const WithdrawPage());
      await flushSnackbars(tester); // 列表/开关两次离线加载失败的提示
      expect(find.text('Error'), findsNothing); // 冲干净了 ⇒ 下面那条 Error 只可能来自本次点击

      await tester.tap(find.byType(Switch));
      await tester.pumpAndSettle();
      expect(tester.widget<Switch>(find.byType(Switch)).value, isFalse); // 开关没动
      expect(find.text('Error'), findsOneWidget); // 但确实打过后端、被拒绝了
      await flushSnackbars(tester);

      final ctrl = Get.find<WithdrawController>();
      ctrl.orders.value = <dynamic>[withdrawOrder(status: 'approved', reviewerId: 7)];
      await tester.pump();
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Approved')), findsOneWidget);

      await tester.tap(rowIcon(Icons.payments_outlined));
      await tester.pumpAndSettle();
      expect(find.textContaining('Execute the PayPal payout for "W20260101"'), findsOneWidget);
      await tester.tap(find.widgetWithText(ElevatedButton, 'Execute payout'));
      await tester.pumpAndSettle();

      expect(ctrl.orders.single['status'], 'approved'); // 行状态没被前端提前改掉
      expect(find.descendant(of: find.byType(DataTable), matching: find.text('Approved')), findsOneWidget);
      expect(find.text('Success'), findsNothing); // 没有假的成功提示
      expect(find.text('Error'), findsOneWidget); // 失败原话来自传输层/服务端

      await flushSnackbars(tester);
    });
  });

  group('阶梯限额', () {
    Map<String, dynamic> tier() => <String, dynamic>{
          'id': 't-1',
          'user_level': 'default',
          'single_min': '1.0000',
          'single_max': '1000.0000',
          'daily_limit': '500.0000',
          'monthly_limit': '5000.0000',
          'fee_pct': '2.5',
          'fee_max': '50.0000',
          'auto_approve_threshold': '0.0000',
        };

    testWidgets('金额/费率字段是 text 控件（键盘类型为空），档位名编辑态置灰', (tester) async {
      final ctrl = _FakeLimitController()..tiers.value = <dynamic>[tier()];
      Get.put<WithdrawLimitController>(ctrl);
      await pumpPage(tester, const WithdrawLimitsTab());

      await tester.tap(find.byIcon(Icons.edit));
      await tester.pumpAndSettle();
      expect(find.text('Edit tier: default'), findsOneWidget);

      for (final label in <String>[
        'Min per order',
        'Max per order',
        'Daily Limit',
        'Monthly limit',
        'Fee rate (%)',
        'Fee cap',
        'Auto Approve Threshold',
      ]) {
        expect(
          tester.widget<TextField>(find.widgetWithText(TextField, label)).keyboardType,
          isNot(TextInputType.number), // 数字键盘会吃掉 DECIMAL 的小数位
          reason: '$label 是金额/费率，必须是 text 控件',
        );
      }
      // 档位名是 install.sql 预置的行，编辑态不可改也不提交
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Tier')).enabled, isFalse);

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
    });

    testWidgets('单档提交：清空的金额不进 payload，档位名不进 payload，其余原样（字符串）回传', (tester) async {
      final ctrl = _FakeLimitController()..tiers.value = <dynamic>[tier()];
      Get.put<WithdrawLimitController>(ctrl);
      await pumpPage(tester, const WithdrawLimitsTab());

      await tester.tap(find.byIcon(Icons.edit));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'Min per order'), '');
      await tester.enterText(find.widgetWithText(TextField, 'Fee cap'), '5.0000');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      final (hashid, data) = ctrl.saved.single;
      expect(hashid, 't-1'); // {hashid} 用行里那个
      expect(data.containsKey('single_min'), isFalse); // 空值不发（numeric 规则会 422，且「空」不是「清零」）
      expect(data['fee_max'], '5.0000');
      expect(data.containsKey('user_level'), isFalse);
      expect(data['single_max'], '1000.0000'); // 金额是字符串原值，没被转成 double
      await flushSnackbars(tester);
    });

    testWidgets('全档位重置：三个都空就什么都不发（不报假成功），填了要先过二次确认（文案点出范围与三个值）', (tester) async {
      final ctrl = _FakeLimitController()..tiers.value = <dynamic>[tier()];
      Get.put<WithdrawLimitController>(ctrl);
      await pumpPage(tester, const WithdrawLimitsTab());

      /// 开框 → （可选）填一个 → Save。Save 后表单进入提交态，确认框叠在它上面，
      /// 表单里是无尽转圈 ⇒ 不能用 pumpAndSettle（见本仓 widget 用例坑 ②）。
      Future<void> openAndSave({String? daily}) async {
        await tester.tap(find.widgetWithText(OutlinedButton, 'Reset all tiers'));
        await tester.pumpAndSettle();
        if (daily != null) {
          await tester.enterText(find.widgetWithText(TextField, 'Daily Limit'), daily);
        }
        await tester.tap(find.text('Save'));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 400));
      }

      // 三个都空 ⇒ 后端「缺省键=不动」，前端一个字节都不发，也不该弹确认框
      await openAndSave();
      expect(find.byType(AlertDialog), findsNothing);
      expect(ctrl.resets, isEmpty);
      expect(find.text('Success'), findsNothing);

      // 一次写穿 default/verified/vip 三档且含 auto_approve_threshold ⇒ 必须二次确认，
      // 且文案要能看出**范围**与**将写入的三个值**（空的那两项写明保持原值）
      await openAndSave(daily: '100.0000');
      expect(
        find.text('Overwrite all withdrawal tiers at once (default / verified / vip)?\n'
            'Daily limit: 100.0000\nMin per order: unchanged\nAuto-approve threshold: unchanged\n'
            'Empty fields keep their current value.'),
        findsOneWidget,
      );

      // 取消 ⇒ 一个字节都不发（确认框叠在表单框之上，两个框都有 Cancel ⇒ 限到最上层那个）
      await tester.tap(find.descendant(
        of: find.byType(AlertDialog).last,
        matching: find.widgetWithText(TextButton, 'Cancel'),
      ));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      expect(ctrl.resets, isEmpty);
      expect(find.text('Success'), findsNothing);

      // 再来一次并确认到底：成功文案是服务端 message，不是本地拼的
      await openAndSave(daily: '100.0000');
      await tester.tap(find.descendant(
        of: find.byType(AlertDialog).last,
        matching: find.widgetWithText(ElevatedButton, 'Reset all tiers'),
      ));
      await tester.pumpAndSettle();
      expect(ctrl.resets.single, <String, dynamic>{'daily_limit': '100.0000'});
      expect(find.text('server: reset ok'), findsOneWidget);

      await flushSnackbars(tester);
    });
  });

  group('支付方式', () {
    testWidgets('金额是 text 控件、排序才是 number；新建默认停用；国家拆行成数组', (tester) async {
      final ctrl = _FakePaymentController();
      Get.put<PaymentController>(ctrl);
      await pumpPage(tester, const PaymentPage());

      await tester.tap(find.text('Create'));
      await tester.pumpAndSettle();
      expect(find.text('Create Payment Method'), findsOneWidget);

      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Min Amount')).keyboardType,
          isNot(TextInputType.number));
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Max Amount')).keyboardType,
          isNot(TextInputType.number));
      // 正控：同样是数字语义的 sort 走 number 控件 ⇒ 上面两条断言确实能分辨控件类型
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Sort')).keyboardType,
          TextInputType.number);

      await tester.enterText(find.widgetWithText(TextField, 'Name'), 'USDT (TRC20)');
      await tester.enterText(find.widgetWithText(TextField, 'Countries'), 'us, cn\njp');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      final data = ctrl.created.single;
      expect(data['name'], 'USDT (TRC20)');
      expect(data['countries'], <String>['US', 'CN', 'JP']); // 拆行 + 大写（C 端 isAvailableIn 精确比较）
      expect(data['type'], 'fiat'); // 下拉默认取第一项（值域 fiat/crypto）
      expect(data['provider'], 'stripe');
      expect(data['status'], 0); // 新建默认停用：网关没核过就不该对用户可见
      expect(data.containsKey('min_amount'), isFalse); // 空金额不提交（numeric 规则会 422）
      expect(data.containsKey('max_amount'), isFalse);
      await flushSnackbars(tester);
    });

    testWidgets('列表：国家为空显示「全球」，有值按数组铺开；编辑态把数组回填成多行文本', (tester) async {
      final ctrl = _FakePaymentController();
      ctrl.methods.value = <dynamic>[
        <String, dynamic>{
          'id': 'm-1',
          'name': 'Stripe',
          'type': 'fiat',
          'provider': 'stripe',
          'status': 1,
          'sort': 1,
          'currency': 'USD',
          'min_amount': '1.0000',
          'max_amount': '500.0000',
          'countries': <dynamic>['US', 'CN'],
        },
        <String, dynamic>{
          'id': 'm-2',
          'name': 'USDT',
          'type': 'crypto',
          'provider': 'nowpayments',
          'status': 0,
          'sort': 2,
          'countries': <dynamic>[],
        },
      ];
      Get.put<PaymentController>(ctrl);
      await pumpPage(tester, const PaymentPage());

      expect(find.text('US, CN'), findsOneWidget);
      expect(find.text('Global (all countries)'), findsOneWidget);
      expect(find.text('Enabled'), findsOneWidget);
      expect(find.text('Disabled'), findsOneWidget);

      // 第二行的行内动作：编辑 → countries 数组回填成「每行一个」
      await tester.tap(find.byIcon(Icons.edit).last);
      await tester.pumpAndSettle();
      expect(find.text('Edit Payment Method'), findsOneWidget);
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Countries')).controller?.text, '');

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(Icons.edit).first);
      await tester.pumpAndSettle();
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Countries')).controller?.text, 'US\nCN');
    });
  });

  group('CDN 厂商', () {
    testWidgets('连通测试：成功/失败都读服务端 message，删除确认带厂商名', (tester) async {
      final ctrl = _FakeCdnController();
      ctrl.providers.value = <dynamic>[
        <String, dynamic>{'id': 'c-1', 'name': 'Cloudflare Prod', 'provider': 'cloudflare', 'status': 1, 'sort': 1},
      ];
      Get.put<CdnController>(ctrl);
      await pumpPage(tester, const CdnPage());

      await tester.tap(find.byIcon(Icons.wifi_tethering));
      await tester.pumpAndSettle();
      expect(find.text('server: cdn probe ok'), findsOneWidget); // 探测结果原样显示，不吞不改写
      expect(ctrl.testingId.value, ''); // 转圈态复位，按钮还能再点
      await flushSnackbars(tester);

      await tester.tap(find.byIcon(Icons.delete));
      await tester.pumpAndSettle();
      expect(find.text('Delete "Cloudflare Prod"?'), findsOneWidget);
      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Cancel')));
      await tester.pumpAndSettle();
      expect(ctrl.removed, isEmpty);
    });
  });

  group('优惠券', () {
    Map<String, dynamic> coupon({String id = 'cp-1', int totalQty = 0}) => <String, dynamic>{
          'id': id,
          'name': 'New Year',
          'type': 'fixed',
          'value': '10.0000',
          'min_amount': '100.0000',
          'max_discount': '0.0000',
          'total_qty': totalQty,
          'used_qty': 3,
          'user_limit': 1,
          'status': 1,
        };

    testWidgets('新建表单里没有状态开关（status 由后端硬编码 1），金额是 text 控件', (tester) async {
      final ctrl = _FakeCouponController();
      Get.put<CouponAdminController>(ctrl);
      await pumpPage(tester, const CouponPage());

      await tester.tap(find.text('Create'));
      await tester.pumpAndSettle();
      expect(find.text('Create Coupon'), findsOneWidget);
      expect(find.descendant(of: find.byType(AlertDialog), matching: find.byType(SwitchListTile)), findsNothing);

      for (final label in <String>['Value / rate', 'Min spend', 'Max discount']) {
        expect(tester.widget<TextField>(find.widgetWithText(TextField, label)).keyboardType,
            isNot(TextInputType.number), reason: '$label 是金额，必须是 text 控件');
      }
      expect(tester.widget<TextField>(find.widgetWithText(TextField, 'Total qty')).keyboardType,
          TextInputType.number);

      await tester.enterText(find.widgetWithText(TextField, 'Name'), 'Spring');
      await tester.enterText(find.widgetWithText(TextField, 'Value / rate'), '8.0000');
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      final data = ctrl.created.single;
      expect(data['name'], 'Spring');
      expect(data['value'], '8.0000');
      expect(data['type'], 'fixed'); // 下拉默认第一项
      expect(data.containsKey('status'), isFalse); // 表单里根本没有这个字段（后端 create 也不读入参）
      expect(data.containsKey('min_amount'), isFalse);
      await flushSnackbars(tester);
    });

    testWidgets('列表：已用/总量按 0=不限量显示，行内开关走局部 PUT，统计弹框读 stats', (tester) async {
      final ctrl = _FakeCouponController()
        ..statsData = <String, dynamic>{'total_qty': 0, 'used_qty': 3, 'remaining': null, 'usage_rate': null};
      ctrl.items.value = <dynamic>[coupon(), coupon(id: 'cp-2', totalQty: 10)];
      Get.put<CouponAdminController>(ctrl);
      await pumpPage(tester, const CouponPage());

      expect(find.text('3 / Unlimited'), findsOneWidget); // 总量 0 = 不限量，不摆「3 / 0」
      expect(find.text('3 / 10'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(DataTable), matching: find.byType(Switch)).first);
      await tester.pumpAndSettle();
      expect(ctrl.statusCalls, <(String, int)>[('cp-1', 0)]); // 行里 status=1 ⇒ 切到 0

      await tester.tap(find.byIcon(Icons.bar_chart).first);
      await tester.pumpAndSettle();
      expect(find.text('Coupon stats: New Year'), findsOneWidget);
      expect(find.text('Unlimited'), findsNWidgets(2)); // total_qty=0 与 remaining=null 都读作不限量
      expect(find.text('Claimed'), findsOneWidget);
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();

      await tester.tap(find.byIcon(Icons.delete).first);
      await tester.pumpAndSettle();
      expect(find.text('Delete coupon "New Year" and all of its user claim records?'), findsOneWidget);
      await tester.tap(find.descendant(of: find.byType(AlertDialog).last, matching: find.text('Delete')));
      await tester.pumpAndSettle();
      expect(ctrl.removed, <String>['cp-1']);
      await flushSnackbars(tester);
    });
  });

  group('确认文案里的金额（纯函数）', () {
    test('批量合计按 4 位小数放大成整数相加：尾数补零、大数不吃精度', () {
      expect(withdrawSumPlatformAmount(<dynamic>[{'platform_amount': '100.0000'}, {'platform_amount': '0.1'}]),
          '100.1000');
      expect(withdrawSumPlatformAmount(<dynamic>[
        {'platform_amount': '999999999999999999.9999'},
        {'platform_amount': '0.0001'},
      ]), '1000000000000000000.0000'); // double 到这里已经开始掉尾数
      expect(withdrawSumPlatformAmount(<dynamic>[<String, dynamic>{}]), '0.0000');
    });

    test('金额文案：没有到账法币时只说平台币，有则带上法币与币种', () {
      expect(withdrawOrderMoney(withdrawOrder()), '100.0000 platform tokens (payout 100.00 USD)');
      expect(withdrawOrderMoney(withdrawOrder(fiat: '0.0000')), '100.0000 platform tokens');
    });

    test('订单标识：订单号优先，缺了就退回 hashid（确认文案必须对得上屏幕上那一行）', () {
      expect(withdrawOrderLabel(withdrawOrder()), 'W20260101');
      expect(withdrawOrderLabel(<String, dynamic>{'id': 'w-9', 'order_no': '  '}), 'w-9');
    });
  });
}
