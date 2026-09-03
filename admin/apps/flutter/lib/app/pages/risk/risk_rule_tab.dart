// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

/// 规则效果：命中/阻断/误判率（近7天）+ 一键启停已有规则
class RiskRuleController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/risk/rule-performance');
      items.value = (resp['data']['items'] as List<dynamic>?) ?? [];
    } catch (e) {
      Get.snackbar('加载失败', '$e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggle(String id) async {
    await api.post('/admin/v1/risk/rule/$id/toggle');
    await load();
  }
}

class RiskRuleTab extends GetView<RiskRuleController> {
  const RiskRuleTab({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskRuleController>()) Get.put(RiskRuleController());
    final ctrl = controller;

    return Obx(() {
      if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
      return Column(children: [
        Expanded(child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: DataTable(
            columns: const [
              DataColumn(label: Text('规则')),
              DataColumn(label: Text('类型')),
              DataColumn(label: Text('动作')),
              DataColumn(label: Text('优先级')),
              DataColumn(label: Text('状态')),
              DataColumn(label: Text('命中')),
              DataColumn(label: Text('阻断率')),
              DataColumn(label: Text('误判率')),
              DataColumn(label: Text('操作')),
            ],
            rows: [
              for (final r in ctrl.items)
                DataRow(cells: [
                  DataCell(Text('${r['name']}')),
                  DataCell(Text('${r['type']}')),
                  DataCell(Text('${r['action']}')),
                  DataCell(Text('${r['priority']}')),
                  DataCell(Text(r['status'] == 1 ? '启用' : '禁用')),
                  DataCell(Text('${r['hits']}')),
                  DataCell(Text('${r['block_rate']}%')),
                  DataCell(Text('${r['manual_review_rate']}%')),
                  DataCell(TextButton(
                    onPressed: () => ctrl.toggle('${r['id']}'),
                    child: Text(r['status'] == 1 ? '禁用' : '启用'),
                  )),
                ]),
            ],
          ),
        )),
        const Padding(
          padding: EdgeInsets.all(8),
          child: Text('误判率口径：近7天内该规则命中中 result=manual_review 的占比（人工复核结论未回写时以 manual_review 计数近似）',
              style: TextStyle(fontSize: 12, color: Colors.grey)),
        ),
      ]);
    });
  }
}
