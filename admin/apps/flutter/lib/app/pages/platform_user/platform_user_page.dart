// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class PlatformUserController extends GetxController {
  final api = ApiService();
  final users = <dynamic>[].obs;
  final isLoading = false.obs;
  final keyword = ''.obs;
  final statusFilter = ''.obs;

  @override
  void onInit() {
    super.onInit();
    loadUsers();
  }

  Future<void> loadUsers() async {
    isLoading.value = true;
    try {
      final params = <String, dynamic>{};
      if (keyword.value.isNotEmpty) params['keyword'] = keyword.value;
      if (statusFilter.value.isNotEmpty) params['status'] = statusFilter.value;
      final resp = await api.get('/admin/platform/user/list', params: params);
      users.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载平台用户失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggleStatus(String hashid, int newStatus) async {
    try {
      await api.put('/admin/platform/user/$hashid', data: {'status': newStatus});
      await loadUsers();
      Get.snackbar('成功', newStatus == 1 ? '用户已启用' : '用户已禁用');
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  Future<Map<String, dynamic>?> getUserDetail(String hashid) async {
    try {
      final resp = await api.get('/admin/platform/user/$hashid');
      return resp['data'] as Map<String, dynamic>?;
    } catch (e) {
      Get.snackbar('错误', '获取用户详情失败: $e');
      return null;
    }
  }
}

class PlatformUserPage extends GetView<PlatformUserController> {
  const PlatformUserPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PlatformUserController>()) {
      Get.put(PlatformUserController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('平台用户', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Row(
          children: [
            SizedBox(
              width: 250,
              child: TextField(
                decoration: const InputDecoration(
                  hintText: '搜索用户名/昵称',
                  prefixIcon: Icon(Icons.search),
                  isDense: true,
                ),
                onSubmitted: (v) {
                  ctrl.keyword.value = v;
                  ctrl.loadUsers();
                },
              ),
            ),
            const SizedBox(width: 12),
            Obx(() => DropdownButton<String>(
              value: ctrl.statusFilter.value.isEmpty ? null : ctrl.statusFilter.value,
              hint: const Text('状态筛选'),
              underline: const SizedBox(),
              items: const [
                DropdownMenuItem(value: '', child: Text('全部')),
                DropdownMenuItem(value: '1', child: Text('启用')),
                DropdownMenuItem(value: '0', child: Text('禁用')),
              ],
              onChanged: (v) {
                ctrl.statusFilter.value = v ?? '';
                ctrl.loadUsers();
              },
            )),
          ],
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.users.isEmpty) return const Center(child: Text('暂无数据'));

            return SingleChildScrollView(
              child: DataTable(
                columns: const [
                  DataColumn(label: Text('ID')),
                  DataColumn(label: Text('用户名')),
                  DataColumn(label: Text('昵称')),
                  DataColumn(label: Text('国家')),
                  DataColumn(label: Text('状态')),
                  DataColumn(label: Text('注册时间')),
                  DataColumn(label: Text('操作')),
                ],
                rows: ctrl.users.map((u) {
                  final id = u['id']?.toString() ?? '';
                  final username = u['username']?.toString() ?? '';
                  final nickname = u['nickname']?.toString() ?? '';
                  final country = u['country']?.toString() ?? '-';
                  final status = u['status'] is int ? u['status'] : (u['status'] == 'active' || u['status'] == true ? 1 : 0);
                  final createdAt = u['created_at']?.toString() ?? '';

                  return DataRow(
                    onSelectChanged: (_) => _showUserDetail(context, ctrl, u),
                    cells: [
                      DataCell(Text(id)),
                      DataCell(Text(username)),
                      DataCell(Text(nickname)),
                      DataCell(Text(country)),
                      DataCell(Chip(
                        label: Text(status == 1 ? '启用' : '禁用'),
                        color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                      )),
                      DataCell(Text(createdAt)),
                      DataCell(
                        TextButton(
                          onPressed: () => ctrl.toggleStatus(id, status == 1 ? 0 : 1),
                          child: Text(
                            status == 1 ? '禁用' : '启用',
                            style: TextStyle(color: status == 1 ? Colors.red : Colors.green),
                          ),
                        ),
                      ),
                    ],
                  );
                }).toList(),
              ),
            );
          }),
        ),
      ],
    );
  }

  void _showUserDetail(BuildContext context, PlatformUserController ctrl, dynamic user) async {
    final id = user['id']?.toString() ?? '';
    final detail = await ctrl.getUserDetail(id);

    if (detail == null) return;

    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('用户详情 - ${detail['username'] ?? user['username']}'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _detailRow('用户名', detail['username']?.toString()),
              _detailRow('昵称', detail['nickname']?.toString()),
              _detailRow('邮箱', detail['email']?.toString()),
              _detailRow('手机号', detail['phone']?.toString()),
              _detailRow('国家', detail['country']?.toString()),
              _detailRow('钱包余额', detail['wallet_balance']?.toString() ?? detail['balance']?.toString()),
              _detailRow('注册时间', detail['created_at']?.toString()),
              _detailRow('状态', detail['status'] == 1 ? '启用' : '禁用'),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('关闭')),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String? value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 80, child: Text('$label：', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
          Expanded(child: Text(value ?? '-', style: const TextStyle(fontSize: 13))),
        ],
      ),
    );
  }
}
