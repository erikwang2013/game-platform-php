/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
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
  final actionCtrl = TextEditingController();
  final pathCtrl = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    _restoreFilters();
    loadLogs();
  }

  @override
  void onClose() {
    actionCtrl.dispose();
    pathCtrl.dispose();
    super.onClose();
  }

  /// 筛选条件持久化：shared_preferences 恢复上次筛选
  Future<void> _restoreFilters() async {
    final prefs = await SharedPreferences.getInstance();
    actionFilter.value = prefs.getString('log_filter.action') ?? '';
    pathFilter.value = prefs.getString('log_filter.path') ?? '';
    actionCtrl.text = actionFilter.value;
    pathCtrl.text = pathFilter.value;
  }

  Future<void> setActionFilter(String v) async {
    actionFilter.value = v;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('log_filter.action', v);
    await loadLogs(reset: true);
  }

  Future<void> setPathFilter(String v) async {
    pathFilter.value = v;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('log_filter.path', v);
    await loadLogs(reset: true);
  }

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
        SizedBox(width: 150, child: TextField(controller: ctrl.actionCtrl, decoration: InputDecoration(hintText: '${AppTranslations.t('log.action_filter')}', isDense: true), onSubmitted: (v) => ctrl.setActionFilter(v))),
        const SizedBox(width: 12),
        SizedBox(width: 200, child: TextField(controller: ctrl.pathCtrl, decoration: InputDecoration(hintText: '${AppTranslations.t('log.path_filter')}', isDense: true), onSubmitted: (v) => ctrl.setPathFilter(v))),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        return SingleChildScrollView(child: DataTable(columns: [
          DataColumn(label: Text('${AppTranslations.t('log.operator')}')),
          DataColumn(label: Text('${AppTranslations.t('log.method')}')),
          DataColumn(label: Text('${AppTranslations.t('log.path')}')),
          DataColumn(label: Text('${AppTranslations.t('log.ip')}')),
          DataColumn(label: Text('${AppTranslations.t('log.time')}')),
        ], rows: ctrl.logs.map((l) => DataRow(cells: [
          DataCell(Text(l['user_name'] ?? '${AppTranslations.t('common.system')}')),
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
