// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class AchievementAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); load(); }

  /// **不加分页**：/achievement/list 是整表端点（无 total），成就定义是有限的几张表，
  /// 且被 C 端成就展示复用 ⇒ 分页会让下游拿到残缺的定义表。
  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/achievement/list');
      items.value = resp['data']['list'] as List<dynamic>;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally { isLoading.value = false; }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/achievement/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateAchievement(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/achievement/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/achievement/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 状态切换走独立端点（body 里 id 是 hashid），与 update 传 status 的不是同一条路。
  /// 停用只影响「后续事件触发是否再授予」，已授予记录与进度不受影响。
  Future<void> toggle(String hashid, int status) async {
    await api.post('/admin/v1/achievement/toggle', data: <String, dynamic>{'id': hashid, 'status': status});
    await load();
  }
}

class AchievementPage extends GetView<AchievementAdminController> {
  const AchievementPage({super.key});

  /// 字段真值取自 AchievementController::create/update 的 validator + game_achievement 列定义：
  /// - key：只有 create 收（`required|regex:/^[a-z0-9_]+$/|max:50`，create 里另有查重），
  ///   update 的规则里没有它 ⇒ 编辑态置灰且不提交
  /// - condition_json：`required|string`（create/update 都是），服务端另判「能 json_decode 成数组」
  ///   ——JSON 是否合法由服务端说了算，422 的 message 直接显示在框内，改完可重试
  /// - points：INT UNSIGNED ⇒ min:0
  /// - status：0/1，create 读入参（默认 1）、update `in:0,1`，本模块另有 toggle 端点
  static const List<CrudField> _fields = <CrudField>[
    CrudField('key', 'achievement.key', required: true, editableOnEdit: false, hint: 'achievement.key_hint'),
    CrudField('name', 'achievement.name', required: true),
    CrudField('description', 'achievement.description', type: CrudFieldType.multiline),
    CrudField('icon', 'achievement.icon', type: CrudFieldType.image),
    CrudField('condition_json', 'achievement.condition',
        type: CrudFieldType.multiline, required: true, hint: 'achievement.condition_hint'),
    CrudField('points', 'achievement.points', type: CrudFieldType.number, required: true),
    CrudField('status', 'game.status', type: CrudFieldType.toggle),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AchievementAdminController>()) Get.put(AchievementAdminController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('achievement.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
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
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final item = ctrl.items[i];
            // 列表里的 id 是 hashid：{hashid} 路径与 toggle 的 id 都用它
            final id = item['id']?.toString() ?? '';
            final name = item['name']?.toString() ?? '';
            final status = item['status'] is int ? item['status'] as int : 0;
            return Card(child: ListTile(
              title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text('${AppTranslations.t('achievement.key')}: ${item['key']}  |  '
                  '${AppTranslations.t('achievement.points')}: ${item['points']}'),
              trailing: CrudRowActions(
                status: status,
                onToggle: (next) => ctrl.toggle(id, next),
                onEdit: () => _openForm(context, ctrl, item: item),
                onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
              ),
            ));
          },
        );
      })),
    ]);
  }

  Future<void> _openForm(BuildContext context, AchievementAdminController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('achievement.create')}' : '${AppTranslations.t('achievement.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateAchievement(item['id'].toString(), data),
    );
  }
}
