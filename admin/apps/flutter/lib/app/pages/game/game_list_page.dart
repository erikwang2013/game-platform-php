// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class GameListController extends GetxController {
  final api = ApiService();
  final games = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/game/list');
      games.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。
  // 提交成功后的列表刷新失败只会弹加载失败，不会污染表单（此处写入已经成功）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/game/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateGame(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/game/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/game/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class GameListPage extends GetView<GameListController> {
  const GameListPage({super.key});

  /// 字段真值取自 GameController::create/update 的 validator + game_game 列定义：
  /// - slug：只有 create 校验它（`regex:/^[a-z0-9_-]+$/` + 查重），PUT 的规则里没有这条
  ///   ⇒ 编辑态置灰且不提交（推了后端也只在 create 里用）
  /// - api_key/api_secret：模型 $hidden，列表不回显、表单里也不该回显（update 另对空串有「不覆盖」保护）
  /// - type：值域含 embedded，旧前端只列了 self/third_party，漏一档
  /// - status/sort/platform/region/sdk_version：同 update validator（0/1、>=0、四平台枚举、max 10/20）
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'game.name', required: true),
    CrudField('slug', 'game.slug', required: true, editableOnEdit: false, hint: 'game.slug_hint'),
    CrudField('type', 'game.type', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('self', 'game.self'),
      CrudOption('embedded', 'game.embedded'),
      CrudOption('third_party', 'game.third_party'),
    ]),
    CrudField('description', 'game.description', type: CrudFieldType.multiline),
    CrudField('cover_image', 'game.cover_image'),
    CrudField('api_endpoint', 'game.api_endpoint'),
    CrudField('platform', 'game.platform', type: CrudFieldType.select, options: <CrudOption>[
      CrudOption('h5', 'game.platform_h5'),
      CrudOption('unity', 'game.platform_unity'),
      CrudOption('web', 'game.platform_web'),
      CrudOption('native', 'game.platform_native'),
    ]),
    CrudField('region', 'game.region'),
    CrudField('sdk_version', 'game.sdk_version'),
    CrudField('sort', 'game.sort', type: CrudFieldType.number),
    CrudField('status', 'game.status', type: CrudFieldType.toggle),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<GameListController>()) {
      Get.put(GameListController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('game.title')}",
          onCreate: () => _openForm(context, ctrl),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.games.isEmpty) {
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
                  DataColumn(label: Text('${AppTranslations.t('game.id')}')),
                  DataColumn(label: Text("${AppTranslations.t('game.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.slug')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.type')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.currencies')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.games.map((g) {
                  final id = g['id']?.toString() ?? '';
                  final name = g['name']?.toString() ?? '';
                  final slug = g['slug']?.toString() ?? '';
                  final type = g['type']?.toString() ?? '';
                  final typeLabel = type == 'self'
                      ? '${AppTranslations.t('game.self')}'
                      : (type == 'embedded'
                          ? '${AppTranslations.t('game.embedded')}'
                          : '${AppTranslations.t('game.third_party')}');
                  final currencyCount = (g['currency_count'] ?? g['currencies'] is List ? (g['currencies'] as List).length : 0).toString();
                  // 列表里的 id 是 hashid：回填 {hashid} 路径用它
                  final status = g['status'] is int ? g['status'] as int : 0;
                  final statusLabel = status == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}";

                  return DataRow(cells: [
                    DataCell(Text(id)),
                    DataCell(Text(name)),
                    DataCell(Text(slug)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Text(currencyCount)),
                    DataCell(Chip(
                      label: Text(statusLabel),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(CrudRowActions(
                      status: status,
                      // 游戏没有独立 toggle 端点：按规格用 update 传 status
                      onToggle: (next) => ctrl.updateGame(id, <String, dynamic>{'status': next}),
                      onEdit: () => _openForm(context, ctrl, item: g),
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

  Future<void> _openForm(BuildContext context, GameListController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('game.create')}' : '${AppTranslations.t('game.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateGame(item['id'].toString(), data),
    );
  }
}
