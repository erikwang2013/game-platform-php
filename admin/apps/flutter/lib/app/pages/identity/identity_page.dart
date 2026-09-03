// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../i18n/translations.dart';

class IdentityController extends GetxController {
  final api = ApiService();
  final list = <dynamic>[].obs;
  final isLoading = false.obs;
  String statusFilter = 'pending';

  @override
  void onInit() { super.onInit(); loadData(); }

  Future<void> loadData() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/identity/list', params: {'status': statusFilter});
      list.value = (resp['data']['list'] as List<dynamic>?) ?? [];
    } catch (e) {
      Get.snackbar('Error', 'Load failed: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> review(String id, String action) async {
    try {
      await api.put('/admin/v1/identity/review', data: {'id': id, 'action': action});
      Get.snackbar('Success', action == 'approve' ? 'Approved' : 'Rejected');
      loadData();
    } catch (e) {
      Get.snackbar('Error', 'Review failed: $e');
    }
  }
}

class IdentityPage extends GetView<IdentityController> {
  const IdentityPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<IdentityController>()) Get.put(IdentityController());
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text('${AppTranslations.t('identity.title')}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'pending', label: Text('Pending')),
            ButtonSegment(value: 'approved', label: Text('Approved')),
            ButtonSegment(value: 'rejected', label: Text('Rejected')),
          ],
          selected: {ctrl.statusFilter},
          onSelectionChanged: (v) { ctrl.statusFilter = v.first; ctrl.loadData(); },
        ),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.list.isEmpty) return const Center(child: Text('No data'));
        return SingleChildScrollView(child: DataTable(columns: const [
          DataColumn(label: Text('User')),
          DataColumn(label: Text('Name')),
          DataColumn(label: Text('ID Type')),
          DataColumn(label: Text('Status')),
          DataColumn(label: Text('Submitted')),
          DataColumn(label: Text('Actions')),
        ], rows: ctrl.list.map((item) {
          final user = item['user'] as Map<String, dynamic>? ?? {};
          return DataRow(cells: [
            DataCell(Text(user['username'] ?? '')),
            DataCell(Text(item['real_name'] ?? '***')),
            DataCell(Text(item['id_type'] ?? '')),
            DataCell(Chip(label: Text(item['status'] ?? ''), color: WidgetStatePropertyAll(
              item['status'] == 'approved' ? Colors.green.shade50 : item['status'] == 'rejected' ? Colors.red.shade50 : Colors.orange.shade50))),
            DataCell(Text(item['created_at']?.toString().substring(0, 10) ?? '')),
            DataCell(item['status'] == 'pending' ? Row(mainAxisSize: MainAxisSize.min, children: [
              IconButton(icon: const Icon(Icons.check, color: Colors.green), onPressed: () => ctrl.review(item['id'], 'approve'), tooltip: 'Approve'),
              IconButton(icon: const Icon(Icons.close, color: Colors.red), onPressed: () => ctrl.review(item['id'], 'reject'), tooltip: 'Reject'),
            ]) : Text(item['review_note'] ?? '')),
          ]);
        }).toList()));
      })),
    ]);
  }
}
