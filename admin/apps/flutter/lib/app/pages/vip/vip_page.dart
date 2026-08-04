// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class VipController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); load(); }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/vip/level/list');
      items.value = resp['data']['list'] as List<dynamic>;
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
    finally { isLoading.value = false; }
  }

  Future<void> save(Map<String, dynamic> item) async {
    try {
      if (item['id'] != null) {
        await api.put('/admin/vip/level/${item['id']}', data: item);
      } else {
        await api.post('/admin/vip/level/create', data: item);
      }
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }

  Future<void> remove(String id) async {
    try {
      await api.delete('/admin/vip/level/$id');
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
    } catch (e) { Get.snackbar('${AppTranslations.t('app.error')}', '$e'); }
  }
}

class VipPage extends GetView<VipController> {
  const VipPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<VipController>()) Get.put(VipController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text("${AppTranslations.t('vip.title')}", style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: Text("${AppTranslations.t('vip.create')}")),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text("${AppTranslations.t('app.no_data')}"));
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final item = ctrl.items[i];
            final benefits = item['benefits'] ?? '';
            return Card(child: ListTile(
              leading: CircleAvatar(child: Text('${item['level'] ?? ''}')),
              title: Text('${item['name']}', style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text('Exp: ${item['required_exp']}\n${benefits}'),
              isThreeLine: true,
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

  void _confirmDelete(BuildContext ctx, VipController ctrl, dynamic item) {
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text("${AppTranslations.t('app.confirm')} ${AppTranslations.t('app.delete')}"),
      content: Text("${AppTranslations.t('vip.delete_confirm')} ${item['name']}?"),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () { ctrl.remove(item['id']); Navigator.pop(ctx); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
      ],
    ));
  }

  void _showDialog(BuildContext ctx, VipController ctrl, {dynamic? item}) {
    final lCtrl = TextEditingController(text: item != null ? '${item['level']}' : '');
    final nCtrl = TextEditingController(text: item?['name'] ?? '');
    final eCtrl = TextEditingController(text: item != null ? '${item['required_exp']}' : '');
    final bCtrl = TextEditingController(text: item?['benefits'] ?? '');
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text(item != null ? "${AppTranslations.t('vip.edit')}" : "${AppTranslations.t('vip.create')}"),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: lCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('vip.level')}'), keyboardType: TextInputType.number, enabled: item == null),
        TextField(controller: nCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('vip.name')}')),
        TextField(controller: eCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('vip.required_exp')}'), keyboardType: TextInputType.number),
        TextField(controller: bCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('vip.benefits')}'), maxLines: 4),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
        ElevatedButton(onPressed: () {
          try {
            if (bCtrl.text.isNotEmpty) jsonDecode(bCtrl.text);
            ctrl.save({'id': item?['id'], 'level': lCtrl.text, 'name': nCtrl.text, 'required_exp': eCtrl.text, 'benefits': bCtrl.text});
            Navigator.pop(ctx);
          } catch (_) {
            Get.snackbar('${AppTranslations.t('app.error')}', 'Benefits must be valid JSON');
          }
        }, child: Text("${AppTranslations.t('app.save')}")),
      ],
    ));
  }
}
