// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class AchievementAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); load(); }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/achievement/list');
      items.value = resp['data']['list'] as List<dynamic>;
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
    finally { isLoading.value = false; }
  }

  Future<void> save(Map<String, dynamic> item) async {
    try {
      if (item['id'] != null) {
        await api.put('/admin/v1/achievement/${item['id']}', data: item);
      } else {
        await api.post('/admin/v1/achievement/create', data: item);
      }
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }

  Future<void> remove(String id) async {
    try {
      await api.delete('/admin/v1/achievement/$id');
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }
}

class AchievementPage extends GetView<AchievementAdminController> {
  const AchievementPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AchievementAdminController>()) Get.put(AchievementAdminController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text("${AppTranslations.t('achievement.title')}", style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: Text("${AppTranslations.t('achievement.create')}")),
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
              subtitle: Text('Key: ${item['key']}  |  Points: ${item['points']}'),
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

  void _confirmDelete(BuildContext ctx, AchievementAdminController ctrl, dynamic item) {
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text("${AppTranslations.t('app.confirm')} ${AppTranslations.t('app.delete')}"),
      content: Text("${AppTranslations.t('achievement.delete_confirm')} ${item['name']}?"),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () { ctrl.remove(item['id']); Navigator.pop(ctx); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
      ],
    ));
  }

  void _showDialog(BuildContext ctx, AchievementAdminController ctrl, {dynamic? item}) {
    final kCtrl = TextEditingController(text: item?['key'] ?? '');
    final nCtrl = TextEditingController(text: item?['name'] ?? '');
    final dCtrl = TextEditingController(text: item?['description'] ?? '');
    final iCtrl = TextEditingController(text: item?['icon'] ?? '');
    final cCtrl = TextEditingController(text: item?['condition_json'] ?? '');
    final pCtrl = TextEditingController(text: item != null ? '${item['points']}' : '');
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text(item != null ? "${AppTranslations.t('achievement.edit')}" : "${AppTranslations.t('achievement.create')}"),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: kCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.key')}'), enabled: item == null),
        TextField(controller: nCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.name')}')),
        TextField(controller: dCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.description')}')),
        TextField(controller: iCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.icon')}')),
        TextField(controller: cCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.condition')}'), maxLines: 3),
        TextField(controller: pCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('achievement.points')}'), keyboardType: TextInputType.number),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () {
          try {
            if (cCtrl.text.isNotEmpty) jsonDecode(cCtrl.text);
            ctrl.save({'id': item?['id'], 'key': kCtrl.text, 'name': nCtrl.text, 'description': dCtrl.text, 'icon': iCtrl.text, 'condition_json': cCtrl.text, 'points': pCtrl.text});
            Navigator.pop(ctx);
          } catch (_) {
            Get.snackbar('${AppTranslations.t('app.error')}', 'Condition must be valid JSON');
          }
        }, child: Text("${AppTranslations.t('app.save')}")),
      ],
    ));
  }
}
