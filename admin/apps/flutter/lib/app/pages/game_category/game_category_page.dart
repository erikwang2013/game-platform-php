// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class GameCategoryAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/game/category/list');
      items.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/game/category/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateCategory(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/game/category/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/game/category/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 分类没有独立 toggle 端点：状态走 update 的局部 PUT（status 规则 in:0,1）。
  /// 行内开关不弹「已保存」——开关本身就是反馈，失败时 CrudRowActions 会弹服务端 message。
  Future<void> updateStatus(String hashid, int status) async {
    await api.put('/admin/v1/game/category/$hashid', data: <String, dynamic>{'status': status});
    await load();
  }
}

class GameCategoryPage extends GetView<GameCategoryAdminController> {
  const GameCategoryPage({super.key});

  /// 字段真值取自 GameCategoryController::create/update 的 validator + game_game_category 列定义：
  /// - slug：只有 create 收（`required|regex:/^[a-z0-9_-]+$/|max:50`，列上有 uk_slug 唯一键），
  ///   update 的规则里没有它 ⇒ 编辑态置灰且不提交
  /// - status：**表单里刻意不放**——create 恒写 `status = 1`、不读入参，放了开关就会让「新建时关掉」
  ///   在界面上成立、在库里不成立。状态改由列表行开关走 update 的局部 PUT。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'game.name', required: true),
    CrudField('slug', 'game.slug', required: true, editableOnEdit: false, hint: 'game_category.slug_hint'),
    CrudField('icon', 'game_category.icon'),
    CrudField('sort', 'game.sort', type: CrudFieldType.number),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<GameCategoryAdminController>()) {
      Get.put(GameCategoryAdminController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('game_category.title')}",
          onCreate: () => _openForm(context, ctrl),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.items.isEmpty) {
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
                  DataColumn(label: Text("${AppTranslations.t('game.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.slug')}")),
                  DataColumn(label: Text("${AppTranslations.t('game_category.icon')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.sort')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.items.map((c) {
                  // 列表里的 id 是 hashid：{hashid} 路径用它
                  final id = c['id']?.toString() ?? '';
                  final name = c['name']?.toString() ?? '';
                  final slug = c['slug']?.toString() ?? '';
                  final icon = c['icon']?.toString() ?? '';
                  final sort = c['sort']?.toString() ?? '0';
                  final status = c['status'] is int ? c['status'] as int : 0;

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    DataCell(Text(slug)),
                    DataCell(Text(icon)),
                    DataCell(Text(sort)),
                    DataCell(Chip(
                      label: Text(status == 1 ? '${AppTranslations.t('app.enabled')}' : '${AppTranslations.t('app.disabled')}'),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.updateStatus(id, next),
                      onEdit: () => _openForm(context, ctrl, item: c),
                      onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
                    )),
                  ]);
                }).toList(),
              ),
            );
          }),
        ),
      ],
    );
  }

  Future<void> _openForm(BuildContext context, GameCategoryAdminController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('game_category.create')}' : '${AppTranslations.t('game_category.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateCategory(item['id'].toString(), data),
    );
  }
}
