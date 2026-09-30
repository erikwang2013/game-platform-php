// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

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
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  /// 启用/停用/改昵称都走同一个端点（PlatformUserController::update，只收 status 与 nickname）。
  /// 传进来的 data 只含改动字段 —— 该端点是局部更新，多发一个没改的字段会把「值相同」也算成一次写入。
  Future<void> updateUser(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/platform/user/$hashid', data: data);
    await loadUsers();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 平台用户注销 —— DELETE /admin/v1/platform/user/{hashid}（PlatformUserController::destroy）。
  ///
  /// 后端拒绝有非零余额的用户（安全要求），拒绝原因在 ApiException.message 里 ——
  /// 原样透出（确认框统一显示），不吞成「操作失败」，否则运营只看到「失败」而不知道该先清余额。
  Future<void> destroyUser(String hashid) async {
    await api.delete('/admin/v1/platform/user/$hashid');
    await loadUsers();
    // 成功以回读到的真实列表为准，不以「请求发出去了」为准
    if (users.any((u) => u['id']?.toString() == hashid)) {
      throw ApiException(-1, '${AppTranslations.t('platform_user.still_in_list')}');
    }
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('platform_user.destroyed')}');
  }

  Future<Map<String, dynamic>?> getUserDetail(String hashid) async {
    try {
      final resp = await api.get('/admin/v1/platform/user/$hashid');
      return resp['data'] as Map<String, dynamic>?;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
      return null;
    }
  }
}

class PlatformUserPage extends GetView<PlatformUserController> {
  const PlatformUserPage({super.key});

  /// 字段真值取自 PlatformUserController::update 的入参收口（PlatformUserController.php:112-134）：
  /// - status：严格只收 0/1（'0'/'1' 也收，其余一律 422）⇒ 0/1 开关是对的控件
  /// - nickname：string，mb_strlen ≤ 50（列宽 game_user.nickname VARCHAR(50) NOT NULL）
  /// 平台用户不能在管理端新建（没有 create 端点）⇒ 清单页无「+ 新建」。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('nickname', 'platform_user.nickname', hint: 'platform_user.nickname_hint'),
    CrudField('status', 'platform_user.status', type: CrudFieldType.toggle),
  ];

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
                  // 注销确认文案用它看清「删的是谁」：username 为主，空则退昵称、再退 ID
                  final what = username.isNotEmpty ? username : (nickname.isNotEmpty ? nickname : id);

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
                      DataCell(CrudRowActions(
                        status: status,
                        // 平台用户没有独立 toggle 端点：局部 PUT 只发 status
                        onToggle: (next) => ctrl.updateUser(id, <String, dynamic>{'status': next}),
                        onEdit: () => _openForm(context, ctrl, item: u),
                        onDelete: () => confirmCrudDelete(context, what: what, onConfirm: () => ctrl.destroyUser(id)),
                      )),
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

  Future<void> _openForm(BuildContext context, PlatformUserController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: '${AppTranslations.t('platform_user.edit')}',
      fields: _fields,
      initial: initial,
      // 同值提交无害：后端先原样比对、$dirty 为空就返回 count 0，不写库（PlatformUserController.php:139-147）
      onSubmit: (data) => ctrl.updateUser(item['id'].toString(), data),
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
