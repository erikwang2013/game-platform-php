/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class LogController extends GetxController {
  final api = ApiService();
  final logs = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;
  final actionFilter = ''.obs;
  final pathFilter = ''.obs;

  @override
  void onInit() { super.onInit(); loadLogs(); }

  Future<void> loadLogs({bool reset = false}) async {
    if (reset) page.value = 1;
    isLoading.value = true;
    try {
      final params = <String, dynamic>{'page': page.value, 'limit': limit.value};
      if (actionFilter.value.isNotEmpty) params['action'] = actionFilter.value;
      if (pathFilter.value.isNotEmpty) params['path'] = pathFilter.value;
      final resp = await api.get('/admin/log', params: params);
      logs.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) { Get.snackbar('错误', '加载失败: $e'); }
    finally { isLoading.value = false; }
  }

  Future<void> nextPage() async { if (page.value * limit.value < total.value) { page.value++; await loadLogs(); } }
  Future<void> prevPage() async { if (page.value > 1) { page.value--; await loadLogs(); } }
}

class LogPage extends GetView<LogController> {
  const LogPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<LogController>()) {
      Get.put(LogController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text("${AppTranslations.t('log.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 12),
      Row(children: [
        SizedBox(width: 150, child: TextField(decoration: const InputDecoration(hintText: '操作筛选', isDense: true), onSubmitted: (v) { ctrl.actionFilter.value = v; ctrl.loadLogs(reset: true); })),
        const SizedBox(width: 12),
        SizedBox(width: 200, child: TextField(decoration: const InputDecoration(hintText: '路径筛选', isDense: true), onSubmitted: (v) { ctrl.pathFilter.value = v; ctrl.loadLogs(reset: true); })),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        return SingleChildScrollView(child: DataTable(columns: const [
          DataColumn(label: Text('操作者')),
          DataColumn(label: Text('方法')),
          DataColumn(label: Text('路径')),
          DataColumn(label: Text('IP')),
          DataColumn(label: Text('时间')),
        ], rows: ctrl.logs.map((l) => DataRow(cells: [
          DataCell(Text(l['user_name'] ?? '系统')),
          DataCell(Chip(label: Text(l['method'] ?? ''))),
          DataCell(Text(l['path'] ?? '')),
          DataCell(Text(l['ip'] ?? '')),
          DataCell(Text(l['created_at'] ?? '')),
        ])).toList()));
      })),
      Obx(() => Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        IconButton(onPressed: ctrl.prevPage, icon: const Icon(Icons.chevron_left)),
        Text('${ctrl.page.value} / ${(ctrl.total.value / ctrl.limit.value).ceil()} (${ctrl.total.value}条)'),
        IconButton(onPressed: ctrl.nextPage, icon: const Icon(Icons.chevron_right)),
      ])),
    ]);
  }
}
