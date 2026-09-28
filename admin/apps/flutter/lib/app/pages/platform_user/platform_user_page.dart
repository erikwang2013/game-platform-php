// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
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
      final resp = await api.get('/admin/v1/platform/user/list', params: params);
      users.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载平台用户失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggleStatus(String hashid, int newStatus) async {
    try {
      await api.put('/admin/v1/platform/user/$hashid', data: {'status': newStatus});
      await loadUsers();
      Get.snackbar('成功', newStatus == 1 ? '用户已启用' : '用户已禁用');
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  /// 平台用户注销 —— DELETE /admin/v1/platform/user/{hashid}（PlatformUserController::destroy）。
  ///
  /// 后端拒绝有非零余额的用户（安全要求），拒绝原因在 ApiException.message 里 ——
  /// 原样透出，不吞成「操作失败」，否则运营只看到「失败」而不知道该先清余额。
  Future<bool> destroyUser(String hashid) async {
    try {
      await api.delete('/admin/v1/platform/user/$hashid');
    } catch (e) {
      Get.snackbar('错误', '注销失败：${e is ApiException ? e.message : e}');
      return false;
    }
    await loadUsers();
    // 成功以回读到的真实列表为准，不以「请求发出去了」为准
    if (users.any((u) => u['id']?.toString() == hashid)) {
      Get.snackbar('错误', '注销请求已提交，但该用户仍在列表中，请刷新确认');
      return false;
    }
    Get.snackbar('成功', '用户已注销');
    return true;
  }

  Future<Map<String, dynamic>?> getUserDetail(String hashid) async {
    try {
      final resp = await api.get('/admin/v1/platform/user/$hashid');
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
        Text("${AppTranslations.t('platform_user.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        Row(
          children: [
            SizedBox(
              width: 250,
              child: TextField(
                decoration: InputDecoration(
                  hintText: '${AppTranslations.t('platform_user.search_hint')}',
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
              hint: Text('${AppTranslations.t('platform_user.filter_status')}'),
              underline: const SizedBox(),
              items: [
                DropdownMenuItem(value: '', child: Text('${AppTranslations.t('withdraw.all')}')),
                DropdownMenuItem(value: '1', child: Text("${AppTranslations.t('app.enabled')}")),
                DropdownMenuItem(value: '0', child: Text("${AppTranslations.t('app.disabled')}")),
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
            if (ctrl.users.isEmpty) {
              return Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Image.asset('assets/mascot.png', width: 120),
                    const SizedBox(height: 12),
                    Text("${AppTranslations.t('app.no_data')}"),
                  ],
                ),
              );
            }

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Text('ID')),
                  DataColumn(label: Text("${AppTranslations.t('user.username')}")),
                  DataColumn(label: Text('${AppTranslations.t('platform_user.nickname')}')),
                  DataColumn(label: Text('${AppTranslations.t('platform_user.country')}')),
                  DataColumn(label: Text('${AppTranslations.t('platform_user.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('platform_user.created')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
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
                        label: Text(status == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"),
                        color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                      )),
                      DataCell(Text(createdAt)),
                      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                        TextButton(
                          onPressed: () => ctrl.toggleStatus(id, status == 1 ? 0 : 1),
                          child: Text(
                            status == 1 ? "${AppTranslations.t('app.disabled')}" : "${AppTranslations.t('app.enabled')}",
                            style: TextStyle(color: status == 1 ? Colors.red : Colors.green),
                          ),
                        ),
                        TextButton(
                          onPressed: () => _confirmDestroy(context, ctrl, id, username),
                          child: Text("${AppTranslations.t('app.delete')}", style: const TextStyle(color: Colors.red)),
                        ),
                      ])),
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

  /// 注销是破坏性且不可撤销的，先确认再发请求（照 user_list_page 的 _confirmDelete 写法）。
  void _confirmDestroy(BuildContext context, PlatformUserController ctrl, String id, String username) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text("${AppTranslations.t('app.confirm')} ${AppTranslations.t('app.delete')}"),
        content: Text('确认注销平台用户「$username」？该操作不可撤销。'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              ctrl.destroyUser(id);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            child: Text("${AppTranslations.t('app.delete')}"),
          ),
        ],
      ),
    );
  }

  void _showUserDetail(BuildContext context, PlatformUserController ctrl, dynamic user) async {
    final id = user['id']?.toString() ?? '';
    final detail = await ctrl.getUserDetail(id);

    if (detail == null || !context.mounted) return;

    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('${AppTranslations.t('platform_user.user_detail')} - ${detail['username'] ?? user['username']}'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _detailRow("${AppTranslations.t('user.username')}", detail['username']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.nickname')}', detail['nickname']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.email')}', detail['email']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.phone')}', detail['phone']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.country')}', detail['country']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.wallet_balance')}', detail['wallet_balance']?.toString() ?? detail['balance']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.created')}', detail['created_at']?.toString()),
              _detailRow('${AppTranslations.t('platform_user.status')}', detail['status'] == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text('${AppTranslations.t('app.close')}')),
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
