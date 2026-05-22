// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class WithdrawController extends GetxController {
  final api = ApiService();
  final orders = <dynamic>[].obs;
  final isLoading = false.obs;
  final statusFilter = 'all'.obs;
  final withdrawEnabled = true.obs;

  @override
  void onInit() {
    super.onInit();
    loadOrders();
    loadSwitch();
  }

  Future<void> loadOrders() async {
    isLoading.value = true;
    try {
      final params = <String, dynamic>{};
      if (statusFilter.value != 'all') {
        params['status'] = statusFilter.value;
      }
      final resp = await api.get('/admin/withdraw/orders', params: params);
      orders.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载提现订单失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadSwitch() async {
    try {
      final resp = await api.get('/admin/withdraw/switch');
      withdrawEnabled.value = resp['data']['enabled'] == true || resp['data']['status'] == 1;
    } catch (_) {}
  }

  Future<void> toggleWithdrawSwitch(bool value) async {
    try {
      await api.put('/admin/withdraw/switch', data: {'enabled': value ? 1 : 0});
      withdrawEnabled.value = value;
      Get.snackbar('成功', value ? '提现已开启' : '提现已关闭');
    } catch (e) {
      withdrawEnabled.value = !value;
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  Future<void> review(String orderId, String action, String note) async {
    try {
      await api.put('/admin/withdraw/review', data: {
        'order_id': orderId,
        'action': action,
        'note': note,
      });
      await loadOrders();
      Get.snackbar('成功', action == 'approve' ? '已通过' : '已拒绝');
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  Future<void> setLimits(Map<String, dynamic> data) async {
    try {
      await api.post('/admin/withdraw/limits/set', data: data);
      Get.snackbar('成功', '限额设置成功');
    } catch (e) {
      Get.snackbar('错误', '设置失败: $e');
    }
  }
}

class WithdrawPage extends GetView<WithdrawController> {
  const WithdrawPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<WithdrawController>()) {
      Get.put(WithdrawController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text("${AppTranslations.t('withdraw.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            Obx(() => Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('全局提现开关', style: TextStyle(fontSize: 13)),
                const SizedBox(width: 8),
                Switch(
                  value: ctrl.withdrawEnabled.value,
                  onChanged: (v) => ctrl.toggleWithdrawSwitch(v),
                ),
              ],
            )),
            const SizedBox(width: 12),
            ElevatedButton.icon(
              onPressed: () => _showLimitsDialog(context, ctrl),
              icon: const Icon(Icons.tune, size: 18),
              label: const Text('限额设置'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        // Status filter segmented buttons
        Obx(() => SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'all', label: Text('全部')),
            ButtonSegment(value: 'pending', label: Text('待审核')),
            ButtonSegment(value: 'approved', label: Text('已通过')),
            ButtonSegment(value: 'rejected', label: Text('已拒绝')),
          ],
          selected: {ctrl.statusFilter.value},
          onSelectionChanged: (v) {
            ctrl.statusFilter.value = v.first;
            ctrl.loadOrders();
          },
        )),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.orders.isEmpty) return const Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: const [
                  DataColumn(label: Text('订单号')),
                  DataColumn(label: Text('用户')),
                  DataColumn(label: Text('金额')),
                  DataColumn(label: Text('方式')),
                  DataColumn(label: Text('状态')),
                  DataColumn(label: Text('申请时间')),
                  DataColumn(label: Text('操作')),
                ],
                rows: ctrl.orders.map((o) {
                  final orderId = o['order_no']?.toString() ?? o['id']?.toString() ?? '';
                  final username = o['username']?.toString() ?? o['user_name']?.toString() ?? '';
                  final amount = o['amount']?.toString() ?? '0';
                  final method = o['method']?.toString() ?? o['withdraw_method']?.toString() ?? '';
                  final status = o['status']?.toString() ?? '';
                  final createdAt = o['created_at']?.toString() ?? '';
                  final isPending = status == 'pending';

                  String statusLabel;
                  Color statusColor;
                  switch (status) {
                    case 'approved':
                      statusLabel = '已通过';
                      statusColor = Colors.green;
                      break;
                    case 'rejected':
                      statusLabel = '已拒绝';
                      statusColor = Colors.red;
                      break;
                    default:
                      statusLabel = '待审核';
                      statusColor = Colors.orange;
                  }

                  return DataRow(cells: [
                    DataCell(Text(orderId)),
                    DataCell(Text(username)),
                    DataCell(Text(amount)),
                    DataCell(Text(method)),
                    DataCell(Chip(
                      label: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 12)),
                      backgroundColor: statusColor.withValues(alpha: 0.1),
                    )),
                    DataCell(Text(createdAt)),
                    DataCell(
                      isPending
                          ? Row(mainAxisSize: MainAxisSize.min, children: [
                              TextButton(
                                onPressed: () => _showReviewDialog(context, ctrl, o, 'approve'),
                                child: const Text('通过', style: TextStyle(color: Colors.green)),
                              ),
                              const SizedBox(width: 4),
                              TextButton(
                                onPressed: () => _showReviewDialog(context, ctrl, o, 'reject'),
                                child: const Text('拒绝', style: TextStyle(color: Colors.red)),
                              ),
                            ])
                          : const Text('-'),
                    ),
                  ]);
                }).toList(),
              ),
            );
          }),
        ),
      ],
    );
  }

  void _showReviewDialog(BuildContext context, WithdrawController ctrl, dynamic order, String action) {
    final noteCtrl = TextEditingController();
    final isApprove = action == 'approve';
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(isApprove ? '确认通过' : '确认拒绝'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('${isApprove ? "通过" : "拒绝"}提现订单「${order['order_no'] ?? order['id']}」？'),
            const SizedBox(height: 12),
            TextField(
              controller: noteCtrl,
              decoration: const InputDecoration(labelText: '备注（可选）'),
              maxLines: 2,
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(
            onPressed: () {
              ctrl.review(
                order['id']?.toString() ?? '',
                action,
                noteCtrl.text,
              );
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: isApprove ? Colors.green : Colors.red,
              foregroundColor: Colors.white,
            ),
            child: Text(isApprove ? '确认通过' : '确认拒绝'),
          ),
        ],
      ),
    );
  }

  void _showLimitsDialog(BuildContext context, WithdrawController ctrl) {
    final dailyCtrl = TextEditingController();
    final minCtrl = TextEditingController();
    final autoCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('限额设置'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: dailyCtrl,
                decoration: const InputDecoration(labelText: '每日限额'),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: minCtrl,
                decoration: const InputDecoration(labelText: '最低提现金额'),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: autoCtrl,
                decoration: const InputDecoration(labelText: '自动通过阈值'),
                keyboardType: TextInputType.number,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(
            onPressed: () {
              final data = <String, dynamic>{};
              if (dailyCtrl.text.isNotEmpty) data['daily_limit'] = double.tryParse(dailyCtrl.text) ?? 0;
              if (minCtrl.text.isNotEmpty) data['min_amount'] = double.tryParse(minCtrl.text) ?? 0;
              if (autoCtrl.text.isNotEmpty) data['auto_approve_threshold'] = double.tryParse(autoCtrl.text) ?? 0;
              ctrl.setLimits(data);
              Navigator.pop(context);
            },
            child: Text("${AppTranslations.t('app.save')}"),
          ),
        ],
      ),
    );
  }
}
