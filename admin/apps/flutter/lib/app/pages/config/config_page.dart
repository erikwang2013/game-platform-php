/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class ConfigController extends GetxController {
  final api = ApiService();
  final configs = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;

  @override
  void onInit() { super.onInit(); loadConfigs(); }

  Future<void> loadConfigs() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/config', params: {'page': page.value, 'limit': limit.value});
      configs.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) { Get.snackbar('错误', '加载失败: $e'); }
    finally { isLoading.value = false; }
  }

  Future<void> save(dynamic item) async {
    try {
      if (item['id'] != null) {
        await api.put('/admin/config/${item['id']}', data: item);
      } else {
        await api.post('/admin/config', data: item);
      }
      await loadConfigs();
      Get.snackbar('成功', '保存成功');
    } catch (e) { Get.snackbar('错误', '保存失败: $e'); }
  }

  Future<void> remove(String id, String pwd) async {
    try {
      await api.delete('/admin/config/$id', data: {'password': pwd});
      await loadConfigs();
      Get.snackbar('成功', '删除成功');
    } catch (e) { Get.snackbar('错误', '删除失败: $e'); }
  }
}

class ConfigPage extends GetView<ConfigController> {
  const ConfigPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<ConfigController>()) {
      Get.put(ConfigController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Text('系统配置', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        ElevatedButton.icon(onPressed: () => _showDialog(context, ctrl), icon: const Icon(Icons.add), label: const Text('新增配置')),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        return ListView.builder(
          itemCount: ctrl.configs.length,
          itemBuilder: (_, i) {
            final c = ctrl.configs[i];
            return Card(child: ListTile(
              title: Text('${c['group']}.${c['key']}', style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text(c['description'] ?? ''),
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                Chip(label: Text(c['type'] ?? 'string')),
                const SizedBox(width: 8),
                Text(c['value'] ?? '', style: const TextStyle(color: Colors.blue)),
                IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showDialog(context, ctrl, item: c)),
                IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () {
                  final p = TextEditingController();
                  showDialog(context: context, builder: (_) => AlertDialog(title: const Text('确认删除'), content: TextField(controller: p, obscureText: true, decoration: const InputDecoration(labelText: '输入密码确认')), actions: [
                    TextButton(onPressed: () => Navigator.pop(context), child: const Text('取消')),
                    ElevatedButton(onPressed: () { ctrl.remove(c['id'], p.text); Navigator.pop(context); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: const Text('删除')),
                  ]));
                }),
              ]),
            ));
          },
        );
      })),
    ]);
  }

  void _showDialog(BuildContext context, ConfigController ctrl, {dynamic? item}) {
    final gCtrl = TextEditingController(text: item?['group'] ?? '');
    final kCtrl = TextEditingController(text: item?['key'] ?? '');
    final vCtrl = TextEditingController(text: item?['value'] ?? '');
    final tCtrl = TextEditingController(text: item?['type'] ?? 'string');
    final dCtrl = TextEditingController(text: item?['description'] ?? '');
    showDialog(context: context, builder: (_) => AlertDialog(
      title: Text(item != null ? '编辑配置' : '新增配置'),
      content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
        TextField(controller: gCtrl, decoration: const InputDecoration(labelText: '分组'), enabled: item == null),
        TextField(controller: kCtrl, decoration: const InputDecoration(labelText: '键'), enabled: item == null),
        TextField(controller: vCtrl, decoration: const InputDecoration(labelText: '值'), maxLines: 3),
        TextField(controller: tCtrl, decoration: const InputDecoration(labelText: '类型')),
        TextField(controller: dCtrl, decoration: const InputDecoration(labelText: '说明')),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('取消')),
        ElevatedButton(onPressed: () {
          ctrl.save({'id': item?['id'], 'group': gCtrl.text, 'key': kCtrl.text, 'value': vCtrl.text, 'type': tCtrl.text, 'description': dCtrl.text});
          Navigator.pop(context);
        }, child: const Text('保存')),
      ],
    ));
  }
}
