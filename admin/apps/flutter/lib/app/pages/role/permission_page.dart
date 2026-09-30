/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class PermissionController extends GetxController {
  final api = ApiService();
  final tree = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  /// GET /admin/v1/permission 返回的是**树**（PermissionController::buildTree），不是平表。
  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/permission');
      tree.value = resp['data'] as List<dynamic>? ?? [];
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/permission', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updatePermission(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/permission/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 删除会级联子权限（PermissionController::destroy 先删 `parent_id = id` 再删自己），
  /// 且要管理员密码 —— 确认文案里两件事都要说清。
  Future<void> remove(String hashid, String password) async {
    await api.delete('/admin/v1/permission/$hashid', data: <String, dynamic>{'password': password});
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class PermissionPage extends GetView<PermissionController> {
  const PermissionPage({super.key});

  /// 字段真值取自 PermissionController::store/update 的 validator（PermissionController.php:50-54 / 93-98）：
  /// - name：`required|string|max:50`（update 是 sometimes）
  /// - slug：只有 store 收（`required|string|max:100`），update 的规则里没有 ⇒ 编辑态置灰且不提交
  /// - type：只有 store 收（`required|in:1,2,3` = 菜单/按钮/接口）⇒ 编辑态置灰且不提交
  /// - parent_id：只有 store 收 ⇒ 建好后不可改（编辑态置灰且不提交）。值是**父权限 hashid**，
  ///   空/0 = 根节点（PermissionController::decodeParentId:147；非法 hashid 直接 400）
  /// - icon：update `sometimes|nullable|string|max:50`
  /// - path：update `sometimes|nullable|string|max:255`
  /// - sort：update `sometimes|nullable|integer|min:0`（store 默认 0）
  List<CrudField> _fields(List<CrudOption> parentOptions) => <CrudField>[
        CrudField('name', 'permission.name', required: true),
        CrudField('slug', 'permission.slug', required: true, editableOnEdit: false, hint: 'permission.slug_hint'),
        CrudField('type', 'permission.type',
            type: CrudFieldType.select,
            required: true,
            editableOnEdit: false,
            options: <CrudOption>[
              CrudOption('1', 'permission.type_menu'),
              CrudOption('2', 'permission.type_button'),
              CrudOption('3', 'permission.type_api'),
            ]),
        // 根节点排第一 ⇒ 新建态的默认值就是根（不选即顶级）
        CrudField('parent_id', 'permission.parent_id',
            type: CrudFieldType.select,
            editableOnEdit: false,
            hint: 'permission.parent_id_hint',
            options: parentOptions),
        CrudField('icon', 'permission.icon'),
        CrudField('path', 'permission.path'),
        CrudField('sort', 'game.sort', type: CrudFieldType.number),
      ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PermissionController>()) {
      Get.put(PermissionController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('permission.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        final rows = flatten(ctrl.tree);
        if (rows.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/mascot.png', width: 120),
                const SizedBox(height: 12),
                Text("${AppTranslations.t('permission.no_data')}"),
              ],
            ),
          );
        }

        return ListView.builder(
          itemCount: rows.length,
          itemBuilder: (_, i) {
            final (depth, p) = rows[i];
            final id = p['id']?.toString() ?? '';
            final name = p['name']?.toString() ?? '';
            return Padding(
              padding: EdgeInsets.only(left: depth * 24.0),
              child: Card(
                child: ListTile(
                  dense: true,
                  leading: Icon(_typeIcon(p['type']), size: 24),
                  title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: Text('${AppTranslations.t('permission.slug')}: ${p['slug']}  |  '
                      '${AppTranslations.t('permission.type')}: ${_typeLabel(p['type'])}  |  '
                      '${AppTranslations.t('permission.path')}: ${p['path'] ?? ''}  |  '
                      '${AppTranslations.t('permission.sort')}: ${p['sort'] ?? 0}'),
                  trailing: CrudRowActions(
                    // game_admin_permission 没有 status 列（值域只有 type 1/2/3）：只有编辑与删除
                    onEdit: () => _openForm(context, ctrl, item: p),
                    onDelete: () async {
                      var password = '';
                      await confirmCrudAction(
                        context,
                        title: '${crudText('app.confirm')} ${crudText('app.delete')}',
                        message: crudText('permission.delete_confirm_target', {'name': name}),
                        confirmLabel: crudText('app.delete'),
                        onPassword: (value) => password = value,
                        onConfirm: () => ctrl.remove(id, password),
                      );
                    },
                  ),
                ),
              ),
            );
          },
        );
      })),
    ]);
  }

  /// 把权限树摊成「缩进层级 + 节点」的行列表（父在前、子紧随其后）。
  /// 公开：角色表单的权限多选也按同一顺序铺选项。
  static List<(int, dynamic)> flatten(List<dynamic> nodes, [int depth = 0]) {
    final rows = <(int, dynamic)>[];
    for (final node in nodes) {
      rows.add((depth, node));
      final children = node is Map ? node['children'] : null;
      if (children is List && children.isNotEmpty) {
        rows.addAll(flatten(children, depth + 1));
      }
    }
    return rows;
  }

  /// type 的值域只有 1/2/3（PermissionController::store `required|in:1,2,3`）。
  static String _typeLabel(dynamic type) {
    switch (type?.toString()) {
      case '1':
        return '${AppTranslations.t('permission.type_menu')}';
      case '2':
        return '${AppTranslations.t('permission.type_button')}';
      case '3':
        return '${AppTranslations.t('permission.type_api')}';
      default:
        return '${type ?? ''}';
    }
  }

  static IconData _typeIcon(dynamic type) {
    switch (type?.toString()) {
      case '1':
        return Icons.folder_outlined;
      case '2':
        return Icons.smart_button_outlined;
      default:
        return Icons.api_outlined;
    }
  }

  Future<void> _openForm(BuildContext context, PermissionController ctrl, {dynamic item}) async {
    // 树没加载成功时现拉一次：否则父级下拉里只剩「根节点」，新建子权限会被静默挂到顶级
    if (ctrl.tree.isEmpty) await ctrl.load();
    if (!context.mounted) return;

    final parentOptions = <CrudOption>[
      const CrudOption('', 'permission.root'),
      for (final (depth, node) in flatten(ctrl.tree))
        CrudOption(node['id'].toString(), '${'  ' * depth}${node['name']}'),
    ];
    final initial = item == null
        ? null
        : <String, dynamic>{
            ...Map<String, dynamic>.from(item as Map),
            // 根节点的 parent_id 后端给的是数字 0，下拉里对应「根节点」这一项（''）
            'parent_id': (item['parent_id'] == null || '${item['parent_id']}' == '0')
                ? ''
                : item['parent_id'].toString(),
          };
    await showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('permission.create')}' : '${AppTranslations.t('permission.edit')}',
      fields: _fields(parentOptions),
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updatePermission(item['id'].toString(), data),
    );
  }
}
