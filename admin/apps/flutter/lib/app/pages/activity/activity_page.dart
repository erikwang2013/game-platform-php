// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

/// 活动管理（最小区间：单页 列表 + 编辑弹窗；不做 stats/resend）
class ActivityAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); load(); }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/activities/list');
      items.value = resp['data']['list'] as List<dynamic>;
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
    finally { isLoading.value = false; }
  }

  Future<void> save(Map<String, dynamic> item) async {
    try {
      if (item['id'] != null) {
        await api.put('/admin/v1/activities/${item['id']}', data: item);
      } else {
        await api.post('/admin/v1/activities/create', data: item);
      }
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }

  Future<void> remove(String id) async {
    try {
      await api.delete('/admin/v1/activities/$id');
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }
}

class ActivityPage extends GetView<ActivityAdminController> {
  const ActivityPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<ActivityAdminController>()) Get.put(ActivityAdminController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Text('运营活动', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: const Text('新建活动')),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text("${AppTranslations.t('app.no_data')}"));
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final item = ctrl.items[i];
            return Card(child: ListTile(
              title: Text('${item['name']}', style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text('Type: ${item['type']}  |  Status: ${item['status']}  |  Rollout: ${item['rollout_percent']}%'),
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showDialog(context, ctrl, item: item)),
                IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _confirmDelete(context, ctrl, item)),
              ]),
            ));
          },
        );
      })),
    ]);
  }

  void _confirmDelete(BuildContext ctx, ActivityAdminController ctrl, dynamic item) {
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text("${AppTranslations.t('app.confirm')} ${AppTranslations.t('app.delete')}"),
      content: Text("确定删除活动 ${item['name']}?"),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () { ctrl.remove(item['id']); Navigator.pop(ctx); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
      ],
    ));
  }

  void _showDialog(BuildContext ctx, ActivityAdminController ctrl, {dynamic item}) {
    final nCtrl = TextEditingController(text: item?['name'] ?? '');
    final typeCtrl = TextEditingController(text: item?['type'] ?? 'signin');
    final gCtrl = TextEditingController(text: item?['game_id'] != null ? '${item?['game_id']}' : '');
    final sCtrl = TextEditingController(text: item?['start_at'] ?? '');
    final eCtrl = TextEditingController(text: item?['end_at'] ?? '');
    final rCtrl = TextEditingController(text: item?['rollout_percent'] != null ? '${item?['rollout_percent']}' : '100');
    final cfgCtrl = TextEditingController(text: item?['config'] != null ? jsonEncode(item['config']) : '');
    var status = item?['status'] ?? 0;

    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text(item != null ? '编辑活动' : '新建活动'),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: nCtrl, decoration: const InputDecoration(labelText: '名称')),
        DropdownButtonFormField<String>(
          initialValue: typeCtrl.text == 'daily_task' ? 'daily_task' : 'signin',
          decoration: const InputDecoration(labelText: '类型'),
          items: const [
            DropdownMenuItem(value: 'signin', child: Text('signin 签到')),
            DropdownMenuItem(value: 'daily_task', child: Text('daily_task 每日任务')),
          ],
          onChanged: (v) => typeCtrl.text = v ?? 'signin',
        ),
        DropdownButtonFormField<int>(
          initialValue: status is int ? status : 0,
          decoration: const InputDecoration(labelText: '状态'),
          items: const [
            DropdownMenuItem(value: 0, child: Text('0 禁用')),
            DropdownMenuItem(value: 1, child: Text('1 启用')),
            DropdownMenuItem(value: 2, child: Text('2 已结束')),
          ],
          onChanged: (v) => status = v ?? 0,
        ),
        TextField(controller: gCtrl, decoration: const InputDecoration(labelText: 'game_id (0=全平台)'), keyboardType: TextInputType.number),
        TextField(controller: sCtrl, decoration: const InputDecoration(labelText: '开始时间 YYYY-MM-DD HH:MM:SS')),
        TextField(controller: eCtrl, decoration: const InputDecoration(labelText: '结束时间 YYYY-MM-DD HH:MM:SS')),
        TextField(controller: rCtrl, decoration: const InputDecoration(labelText: '灰度百分比 0-100'), keyboardType: TextInputType.number),
        TextField(controller: cfgCtrl, decoration: const InputDecoration(labelText: 'config JSON'), maxLines: 5),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () {
          try {
            if (cfgCtrl.text.isNotEmpty) jsonDecode(cfgCtrl.text);
            ctrl.save({
              'id': item?['id'],
              'name': nCtrl.text,
              'type': typeCtrl.text,
              'game_id': gCtrl.text.isEmpty ? 0 : int.parse(gCtrl.text),
              'status': status,
              'start_at': sCtrl.text,
              'end_at': eCtrl.text,
              'rollout_percent': rCtrl.text.isEmpty ? 100 : int.parse(rCtrl.text),
              'config': cfgCtrl.text,
            });
            Navigator.pop(ctx);
          } catch (_) {
            Get.snackbar('${AppTranslations.t('app.error')}', 'config must be valid JSON');
          }
        }, child: Text("${AppTranslations.t('app.save')}")),
      ],
    ));
  }
}
