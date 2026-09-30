/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../widgets/crud.dart';
import 'permission_page.dart';
import 'role_controller.dart';

/// 导航项「角色权限」的落地页：角色与权限两张表同属一个入口（侧边栏文案本就是 Roles & Permissions），
/// 用 TabBar 分成两页，避免再占一个导航位（也避免导航/命令面板的下标整体挪位）。
class RoleListPage extends StatelessWidget {
  const RoleListPage({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(children: [
        TabBar(
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          tabs: [
            Tab(text: '${AppTranslations.t('role.title')}'),
            Tab(text: '${AppTranslations.t('permission.title')}'),
          ],
        ),
        const SizedBox(height: 12),
        const Expanded(child: TabBarView(children: [RoleTab(), PermissionPage()])),
      ]),
    );
  }
}

class RoleTab extends GetView<RoleController> {
  const RoleTab({super.key});

  /// 字段真值取自 RoleController::store/update 的 validator（RoleController.php:60-63 / 118-123）：
  /// - name：两处都是 `required|string|max:50`（update 是 sometimes）
  /// - slug：只有 store 收（`required|string|max:50`），update 的规则里没有 ⇒ 编辑态置灰且不提交
  /// - description：update `sometimes|nullable|string|max:255`（store 未校验，直接落库）
  /// - status：update `sometimes|required|integer|in:0,1`（store 默认 1）⇒ 0/1 开关
  /// - permission_ids：update `sometimes|array`，元素是**权限 hashid** —— 后端已改成 hashid 口径
  ///   （RoleController::decodePermissionIds:145 逐个 decodeId，非法 hashid 直接 400），
  ///   列表也回传 hashid 形式的 permission_ids（index:43-46）供编辑态回填。
  ///   后端 `sync()` 是**整体替换**：表单提交的是完整勾选集，不是增量。
  List<CrudField> _fields(List<CrudOption> permissionOptions) => <CrudField>[
        CrudField('name', 'role.name', required: true),
        CrudField('slug', 'role.slug', required: true, editableOnEdit: false),
        CrudField('description', 'role.description', type: CrudFieldType.multiline),
        CrudField('status', 'game.status', type: CrudFieldType.toggle),
        // 权限树没取到时**不摆这个字段**：空勾选集一旦被提交就等于把角色的权限整体清空
        if (permissionOptions.isNotEmpty)
          CrudField('permission_ids', 'role.permission_ids',
              type: CrudFieldType.multiselect, hint: 'role.permission_ids_hint', options: permissionOptions),
      ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RoleController>()) {
      Get.put(RoleController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('role.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.roles.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/mascot.png', width: 120),
                const SizedBox(height: 12),
                Text("${AppTranslations.t('role.no_roles')}"),
              ],
            ),
          );
        }

        return ListView.builder(
          itemCount: ctrl.roles.length,
          itemBuilder: (_, i) {
            final r = ctrl.roles[i];
            // 列表里的 id 是 hashid：{hashid} 路径用它
            final id = r['id']?.toString() ?? '';
            final name = r['name']?.toString() ?? '';
            final status = r['status'] is int ? r['status'] as int : 0;
            return Card(
              child: ListTile(
                leading: const Icon(Icons.shield, size: 36),
                title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
                subtitle: Text('${AppTranslations.t('role.identifier')}: ${r['slug']}  |  '
                    '${AppTranslations.t('role.users_count')}: ${r['users_count'] ?? 0}  |  ${r['description'] ?? ''}'),
                trailing: CrudRowActions(
                  status: status,
                  // 角色没有独立 toggle 端点：按规格局部 PUT 传 status
                  onToggle: (next) => ctrl.updateRole(id, <String, dynamic>{'status': next}),
                  onEdit: () => _openForm(context, ctrl, role: r),
                  // 删除要管理员密码：密码经确认框回传（空密码服务端直接 422）
                  onDelete: () async {
                    var password = '';
                    await confirmCrudDelete(
                      context,
                      what: name,
                      onPassword: (value) => password = value,
                      onConfirm: () => ctrl.destroyRole(id, password),
                    );
                  },
                ),
              ),
            );
          },
        );
      })),
    ]);
  }

  Future<void> _openForm(BuildContext context, RoleController ctrl, {dynamic role}) async {
    final permissionOptions = await _permissionOptions();
    if (!context.mounted) return;
    final initial = role == null ? null : Map<String, dynamic>.from(role as Map);
    await showCrudForm(
      context,
      title: role == null ? '${AppTranslations.t('role.create')}' : '${AppTranslations.t('role.edit')}',
      fields: _fields(permissionOptions),
      initial: initial,
      onSubmit: (data) => role == null ? ctrl.createRole(data) : ctrl.updateRole(role['id'].toString(), data),
    );
  }

  /// 权限多选的值域 = 权限树摊平后的「缩进 + 名称」。
  /// 复用权限页的 PermissionController：先点过权限页就免一次请求，没点过就现拉一次。
  /// label 传成品文案（crudText 查不到 key 会原样显示）—— 名称来自库，不是 i18n key。
  Future<List<CrudOption>> _permissionOptions() async {
    final permCtrl = Get.isRegistered<PermissionController>()
        ? Get.find<PermissionController>()
        : Get.put(PermissionController());
    if (permCtrl.tree.isEmpty) await permCtrl.load();
    return <CrudOption>[
      for (final (depth, node) in PermissionPage.flatten(permCtrl.tree))
        CrudOption(node['id'].toString(), '${'  ' * depth}${node['name']}'),
    ];
  }
}
