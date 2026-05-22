/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'user_controller.dart';
import 'user_form_page.dart';

class UserListPage extends GetView<UserController> {
  const UserListPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<UserController>()) {
      Get.put(UserController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header
        Row(
          children: [
            Text("${AppTranslations.t('user.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            ElevatedButton.icon(
              onPressed: () => Get.to(() => const UserFormPage())?.then((_) => ctrl.loadUsers(reset: true)),
              icon: const Icon(Icons.add),
              label: Text("${AppTranslations.t('user.create')}"),
            ),
            const SizedBox(width: 8),
            if (ctrl.selectedIds.isNotEmpty) ...[
              ElevatedButton.icon(
                onPressed: () => _confirmBatchDelete(context, ctrl),
                icon: const Icon(Icons.delete, color: Colors.red),
                label: Text('删除(${ctrl.selectedIds.length})'),
                style: ElevatedButton.styleFrom(foregroundColor: Colors.red),
              ),
              const SizedBox(width: 8),
              PopupMenuButton<String>(
                onSelected: (v) {
                  if (v == 'enable') ctrl.batchSetStatus(1);
                  if (v == 'disable') ctrl.batchSetStatus(0);
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'enable', child: Text('${AppTranslations.t('user.batch_enable')}')),
                  PopupMenuItem(value: 'disable', child: Text('${AppTranslations.t('user.batch_disable')}')),
                ],
              ),
            ],
          ],
        ),
        const SizedBox(height: 12),
        // Search + Filter
        Row(
          children: [
            SizedBox(
              width: 250,
              child: TextField(
                decoration: const InputDecoration(hintText: '${AppTranslations.t('user.search_hint')}', prefixIcon: Icon(Icons.search), isDense: true),
                onSubmitted: (v) => ctrl.search(v),
              ),
            ),
            const SizedBox(width: 12),
            ChoiceChip(label: Text('${AppTranslations.t('withdraw.all')}'), selected: ctrl.statusFilter.value == null, onSelected: (_) => ctrl.filterByStatus(null)),
            const SizedBox(width: 4),
            ChoiceChip(label: const Text("${AppTranslations.t('app.enabled')}"), selected: ctrl.statusFilter.value == 1, onSelected: (_) => ctrl.filterByStatus(1)),
            const SizedBox(width: 4),
            ChoiceChip(label: const Text("${AppTranslations.t('app.disabled')}"), selected: ctrl.statusFilter.value == 0, onSelected: (_) => ctrl.filterByStatus(0)),
          ],
        ),
        const SizedBox(height: 12),
        // Table
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.users.isEmpty) return const Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Checkbox(value: ctrl.selectedIds.length == ctrl.users.length && ctrl.users.isNotEmpty, onChanged: (_) => ctrl.toggleSelectAll())),
                  const DataColumn(label: Text("${AppTranslations.t('user.username')}")),
                  const DataColumn(label: Text('${AppTranslations.t('user.real_name')}')),
                  const DataColumn(label: Text('${AppTranslations.t('user.phone')}')),
                  const DataColumn(label: Text('${AppTranslations.t('user.email')}')),
                  const DataColumn(label: Text('${AppTranslations.t('user.status')}')),
                  const DataColumn(label: Text('${AppTranslations.t('user.last_login')}')),
                  const DataColumn(label: Text('${AppTranslations.t('user.actions')}')),
                ],
                rows: ctrl.users.map((u) {
                  final id = u['id'].toString();
                  return DataRow(
                    selected: ctrl.selectedIds.contains(id),
                    onSelectChanged: (_) => ctrl.toggleSelect(id),
                    cells: [
                      DataCell(Checkbox(value: ctrl.selectedIds.contains(id), onChanged: (_) => ctrl.toggleSelect(id))),
                      DataCell(Text(u['username'] ?? '')),
                      DataCell(Text(u['real_name'] ?? '')),
                      DataCell(Text(u['phone'] ?? '')),
                      DataCell(Text(u['email'] ?? '')),
                      DataCell(Chip(label: Text(u['status'] == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"), color: WidgetStatePropertyAll(u['status'] == 1 ? Colors.green.shade50 : Colors.red.shade50))),
                      DataCell(Text(u['last_login_at'] ?? '-')),
                      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                        IconButton(icon: const Icon(Icons.edit, size: 18), onPressed: () => Get.to(() => UserFormPage(userData: u))?.then((_) => ctrl.loadUsers())),
                        IconButton(icon: const Icon(Icons.delete, size: 18, color: Colors.red), onPressed: () => _confirmDelete(context, ctrl, u)),
                      ])),
                    ],
                  );
                }).toList(),
              ),
            );
          }),
        ),
        // Pagination
        const SizedBox(height: 8),
        Obx(() => Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(onPressed: ctrl.prevPage, icon: const Icon(Icons.chevron_left)),
            Text('第 ${ctrl.page.value} 页 / 共 ${(ctrl.total.value / ctrl.limit.value).ceil()} 页 (${ctrl.total.value} 条)'),
            IconButton(onPressed: ctrl.nextPage, icon: const Icon(Icons.chevron_right)),
          ],
        )),
      ],
    );
  }

  void _confirmDelete(BuildContext context, UserController ctrl, dynamic user) {
    final pwdCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text("${AppTranslations.t('app.confirm')}"
          + ' '
          + "${AppTranslations.t('app.delete')}",
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('确定要删除用户「${user['username']}」吗？'),
          const SizedBox(height: 8),
          TextField(controller: pwdCtrl, obscureText: true, decoration: const InputDecoration(labelText: '${AppTranslations.t('user.password_confirm_hint')}')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(onPressed: () { ctrl.deleteUser(user['id'], pwdCtrl.text); Navigator.pop(context); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
        ],
      ),
    );
  }

  void _confirmBatchDelete(BuildContext context, UserController ctrl) {
    final pwdCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('${AppTranslations.t('user.confirm_batch_delete')}'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('确定要删除选中的 ${ctrl.selectedIds.length} 个用户吗？'),
          const SizedBox(height: 8),
          TextField(controller: pwdCtrl, obscureText: true, decoration: const InputDecoration(labelText: '${AppTranslations.t('user.password_confirm_hint')}')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(onPressed: () { ctrl.batchDelete(pwdCtrl.text); Navigator.pop(context); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text("${AppTranslations.t('app.delete')}")),
        ],
      ),
    );
  }
}
