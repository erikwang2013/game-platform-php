// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 提现订单的状态与写操作（审核 / 打款 / 打款同步 / 全局开关）+ 订单文案助手。
// 从 withdraw_page.dart 切出来纯粹是因为那个文件越过了 500 行 —— 页面 export 了本文件，
// 原来 import withdraw_page.dart 的地方照旧能看到这里的一切。
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';


/// 订单标识：订单号（列表里就摆着）优先，退回 hashid —— 确认文案必须与屏幕上的那一行对得上。
String withdrawOrderLabel(dynamic order) {
  final no = order['order_no']?.toString().trim() ?? '';
  return no.isNotEmpty ? no : (order['id']?.toString() ?? '');
}

/// 金额文案：平台币 + 到账法币。全是 DECIMAL 原值的字符串透传 —— 金额不参与任何前端运算。
String withdrawOrderMoney(dynamic order) {
  final platform = order['platform_amount']?.toString() ?? '—';
  final fiat = order['fiat_amount']?.toString().trim() ?? '';
  final currency = order['currency']?.toString().trim() ?? '';
  final token = crudText('withdraw.platform_token');
  if (fiat.isEmpty || fiat == '0.0000') return '$platform $token';
  return '$platform $token${crudText('withdraw.fiat_arrival', {'amount': fiat, 'currency': currency})}';
}

/// 勾选行的平台币合计（只进确认文案）。按 4 位小数放大成整数相加：double 累加会吃掉尾数，
/// 而这里是 DECIMAL(18,4) 的字符串原值。
String withdrawSumPlatformAmount(List<dynamic> rows) {
  var total = BigInt.zero;
  for (final row in rows) {
    final parts = (row['platform_amount']?.toString() ?? '0').split('.');
    final frac = parts.length > 1 ? parts[1].padRight(4, '0').substring(0, 4) : '0000';
    total += BigInt.parse('${parts[0]}$frac');
  }
  final digits = total.toString().padLeft(5, '0');
  return '${digits.substring(0, digits.length - 4)}.${digits.substring(digits.length - 4)}';
}

/// 状态/打款状态的显示词：值域外的值原样显示（crudText 查不到 key 会回退成 key 本身）。
///
/// 不用 `_` 前缀：页面在同一目录的**另一个库**里，Dart 的私有名不跨库、`export` 也不转出。
const Map<String, String> withdrawStatusLabels = <String, String>{
  'pending': 'withdraw.pending',
  'approved': 'withdraw.approved',
  'processing': 'withdraw.processing',
  'completed': 'withdraw.completed',
  'rejected': 'withdraw.rejected',
};

const Map<String, String> withdrawPayoutLabels = <String, String>{
  'processing': 'withdraw.payout_processing',
  'success': 'withdraw.payout_success',
  'failed': 'withdraw.payout_failed',
};

class WithdrawController extends GetxController {
  final api = ApiService();
  final orders = <dynamic>[].obs;

  /// 批量审核的勾选集（订单 hashid）。RxSet 的增删本身会通知，无需整表替换。
  final selected = <String>{}.obs;
  final isLoading = false.obs;
  final statusFilter = 'all'.obs;
  final total = 0.obs;
  final page = 1.obs;

  /// 与后端缺省一致（WithdrawController::orders 的 `input('limit', 15)`）。
  static const int pageSize = 15;

  /// 全局开关的读数：以服务端为准（GET 与 PUT 共用 /withdraw/switch）。
  final withdrawEnabled = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadOrders();
    loadSwitch();
  }

  Future<void> loadOrders({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final params = <String, dynamic>{};
      if (statusFilter.value != 'all') {
        params['status'] = statusFilter.value;
      }
      final result = await api.list('/admin/v1/withdraw/orders',
          page: page.value, pageSize: pageSize, params: params);
      orders.value = result.rows;
      total.value = result.total;
      // 换了筛选/刷新后旧的勾选可能已经不在列表里：留着它，批量按钮会拿一批看不见的订单去审核
      selected.clear();
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadSwitch() async {
    try {
      final resp = await api.get('/admin/v1/withdraw/switch');
      // 响应里 enabled/status/global_switch 是同一个布尔值的三个键名（WithdrawController::switchState）
      withdrawEnabled.value = resp['data']['enabled'] == true;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    }
  }

  /// 全局提现开关。**不做乐观切换**：开关的值只由服务端响应决定，失败时保持原位（无需回滚）。
  Future<void> setSwitch(int next) async {
    try {
      final resp = await api.put('/admin/v1/withdraw/switch', data: <String, dynamic>{'enabled': next});
      withdrawEnabled.value = resp['data']['enabled'] == true;
      Get.snackbar('${AppTranslations.t('app.success')}', resp['message']?.toString() ?? '');
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', apiErrorMessage(e));
    }
  }

  /// 单笔审核：action ∈ approve|reject|confirm（WithdrawReviewTrait::review 的 in: 值域）。
  /// 返回服务端 message —— 三种动作话术不同（「审核通过」/「已驳回并退款」/「二次确认通过」），
  /// 双审平台下 approve 只是初审，前端不许替服务端宣布结果。
  Future<String> review(String orderId, String action, String note) async {
    final resp = await api.put('/admin/v1/withdraw/review', data: <String, dynamic>{
      'order_id': orderId,
      'action': action,
      'note': note,
    });
    await loadOrders();
    return resp['message']?.toString() ?? '';
  }

  /// 批量审核：**只收 approve|reject**（batch-review 的 validator 没有 confirm）。
  /// 返回服务端 message（形如「批量处理完成: 2 笔, 失败 1 笔」—— 失败数由服务端数，不在前端算）。
  Future<String> batchReview(List<String> ids, String action, String note) async {
    final resp = await api.post('/admin/v1/withdraw/batch-review', data: <String, dynamic>{
      'ids': ids,
      'action': action,
      'note': note,
    });
    await loadOrders();
    return resp['message']?.toString() ?? '';
  }

  /// 执行打款（真正出钱）。服务端 message 是「打款成功」或「打款已提交」，原样显示。
  Future<String> executePayout(String orderId) async {
    final resp = await api.post('/admin/v1/withdraw/execute-payout', data: <String, dynamic>{'order_id': orderId});
    await loadOrders();
    return resp['message']?.toString() ?? '';
  }

  /// 同步打款状态：该端点的 message 是占位「success」，有用的是 data 里的三个状态
  /// （payout_status / order_status / synced_status）⇒ 返回 data，由页面拼文案。
  Future<Map<String, dynamic>> syncPayout(String orderId) async {
    final resp = await api.post('/admin/v1/withdraw/sync-payout', data: <String, dynamic>{'order_id': orderId});
    await loadOrders();
    return Map<String, dynamic>.from(resp['data'] as Map? ?? <String, dynamic>{});
  }

  /// 列表里被勾选的那些行（保持列表顺序）。
  List<dynamic> selectedOrders() => <dynamic>[
        for (final order in orders)
          if (selected.contains(order['id']?.toString())) order,
      ];
}

