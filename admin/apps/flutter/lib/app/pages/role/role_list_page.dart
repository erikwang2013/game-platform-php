/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'role_controller.dart';

class RoleListPage extends GetView<RoleController> {
  const RoleListPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RoleController>()) {
      Get.put(RoleController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(children: [
          Text("${AppTranslations.t('role.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const Spacer(),
          ElevatedButton.icon(
            onPressed: () => _showRoleDialog(context, ctrl),
            icon: const Icon(Icons.add),
            label: Text("${AppTranslations.t('role.create')}"),
          ),
        ]),
        const SizedBox(height: 12),
        Expanded(child: Obx(() {
          if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
          if (ctrl.roles.isEmpty) return const Center(child: Text("${AppTranslations.t('role.no_roles')}"));

          return ListView.builder(
            itemCount: ctrl.roles.length,
            itemBuilder: (_, i) {
              final r = ctrl.roles[i];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.shield, size: 36),
                  title: Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text('标识: ${r['slug']} | 用户数: ${r['users_count'] ?? 0} | ${r['description'] ?? ''}'),
                  trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                    Chip(label: Text(r['status'] == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}")),
                    IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => _showRoleDialog(context, ctrl, role: r)),
                    IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () {
                      final pwdCtrl = TextEditingController();
                      showDialog(context: context, builder: (_) => AlertDialog(
                        title: Text("${AppTranslations.t('app.confirm')}"
          + ' '
          + "${AppTranslations.t('app.delete')}", content: Column(mainAxisSize: MainAxisSize.min, children: [
                          Text('确定要删除角色「${r['name']}」吗？'),
                          TextField(controller: pwdCtrl, obscureText: true, decoration: const InputDecoration(labelText: '${AppTranslations.t('user.password_confirm_hint')}')),
                        ]),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
                          ElevatedButton(onPressed: () { ctrl.deleteRole(r['id'], pwdCtrl.text); Navigator.pop(context); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
                        ],
                      ));
                    }),
                  ]),
                ),
              );
            },
          );
        })),
      ],
    );
  }

  void _showRoleDialog(BuildContext context, RoleController ctrl, {dynamic? role}) {
    final nameCtrl = TextEditingController(text: role?['name'] ?? '');
    final slugCtrl = TextEditingController(text: role?['slug'] ?? '');
    final descCtrl = TextEditingController(text: role?['description'] ?? '');
    final selectedPerms = (role?['permissions'] as List<dynamic>?)?.map((p) => p['id'].toString()).toSet() ?? <String>{};

    showDialog(
      context: context,
      builder: (_) => StatefulBuilder(
        builder: (_, setDialogState) => AlertDialog(
          title: Text(role != null ? '${AppTranslations.t('role.edit')}' : '${AppTranslations.t('role.create')}', style: const TextStyle(fontWeight: FontWeight.bold)),
          content: SizedBox(width: 450, child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(controller: nameCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('role.name')}'), enabled: role == null),
            TextField(controller: slugCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('role.slug')}'), enabled: role == null),
            TextField(controller: descCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('role.description')}')),
            const SizedBox(height: 12),
            Text('${AppTranslations.t('role.permissions')}:', style: TextStyle(fontWeight: FontWeight.bold)),
            ...ctrl.permissions.map((perm) => CheckboxListTile(
              title: Text(perm['name'] ?? ''),
              subtitle: Text(perm['slug'] ?? ''),
              value: selectedPerms.contains(perm['id'].toString()),
              onChanged: (v) {
                setDialogState(() { if (v == true) { selectedPerms.add(perm['id'].toString()); } else { selectedPerms.remove(perm['id'].toString()); } });
              },
            )),
          ]))),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
            ElevatedButton(onPressed: () {
              if (role != null) {
                ctrl.updateRole(role['id'], name: nameCtrl.text, desc: descCtrl.text, permIds: selectedPerms.toList());
              } else {
                ctrl.createRole(nameCtrl.text, slugCtrl.text, descCtrl.text, selectedPerms.toList());
              }
              Navigator.pop(context);
            }, child: Text("${AppTranslations.t('app.save')}")),
          ],
        ),
      ),
    );
  }
}
