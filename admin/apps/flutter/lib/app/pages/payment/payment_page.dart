// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class PaymentController extends GetxController {
  final api = ApiService();
  final methods = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadMethods();
  }

  Future<void> loadMethods() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/payment/method/list');
      methods.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载支付方式失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggleMethod(String hashid, bool enabled) async {
    try {
      await api.post('/admin/payment/method/toggle', data: {
        'id': hashid,
        'status': enabled ? 1 : 0,
      });
      await loadMethods();
      Get.snackbar('成功', enabled ? '支付方式已启用' : '支付方式已禁用');
    } catch (e) {
      await loadMethods();
      Get.snackbar('错误', '操作失败: $e');
    }
  }
}

class PaymentPage extends GetView<PaymentController> {
  const PaymentPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PaymentController>()) {
      Get.put(PaymentController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text("${AppTranslations.t('payment.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.methods.isEmpty) return const Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: const [
                  DataColumn(label: Text("${AppTranslations.t('game.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.type')}")),
                  DataColumn(label: Text('${AppTranslations.t('payment.provider')}')),
                  DataColumn(label: Text('${AppTranslations.t('payment.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
                ],
                rows: ctrl.methods.map((m) {
                  final name = m['name']?.toString() ?? '';
                  final type = m['type']?.toString() ?? '';
                  final typeLabel = type == 'fiat' ? '${AppTranslations.t('payment.fiat')}' : '${AppTranslations.t('payment.crypto')}';
                  final provider = m['provider']?.toString() ?? '';
                  final status = m['status'] is int ? m['status'] : (m['status'] == 'active' || m['status'] == true ? 1 : 0);
                  final isEnabled = status == 1;

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Text(provider)),
                    DataCell(Chip(
                      label: Text(isEnabled ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"),
                      color: WidgetStatePropertyAll(isEnabled ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(
                      Switch(
                        value: isEnabled,
                        onChanged: (v) => ctrl.toggleMethod(m['id']?.toString() ?? '', v),
                      ),
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
}
