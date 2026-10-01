// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

/// 活动管理（最小区间：不做 stats/resend）
class ActivityAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;

  /// 与后端缺省一致（ActivityController::index 的 `input('limit', 15)`）。
  /// 注意：该端点只回 `{list, total}`（没有 page/limit 回显），总数仍够算页数。
  static const int pageSize = 15;

  @override
  void onInit() { super.onInit(); load(); }

  Future<void> load({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final result = await api.list('/admin/v1/activities/list', page: page.value, pageSize: pageSize);
      items.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally { isLoading.value = false; }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/activities/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateActivity(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/activities/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/activities/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class ActivityPage extends GetView<ActivityAdminController> {
  const ActivityPage({super.key});

  /// 字段真值取自 ActivityController::create/update 的 validator + game_activity 列定义：
  /// - type：`required|in:signin,daily_task,invite`（**三档**，旧前端只列了 signin/daily_task，
  ///   行里若是 invite，值域外的旧控件会断言崩溃）；update 的规则里没有 type ⇒ 编辑态置灰且不提交
  /// - status：`required|integer|in:0,1,2`（0 禁用 / 1 启用 / 2 已结束）——三值枚举，Switch 表达不了，
  ///   本模块也没有 toggle 端点 ⇒ 状态只在表单里改
  /// - game_id：**整数**不是 hashid（create `nullable|integer`、update `sometimes|nullable|integer|min:0`），
  ///   0 = 全平台；列与列表回的都是原始整数，界面直接照数字编辑
  /// - config：JSON，服务端按 type 逐条校验（signin 要 rewards[]、daily_task 要 tasks[]、
  ///   invite 要 target + rewards[]，reward.type 只认 platform_coin/game_coin、amount ∈ (0,10000]）；
  ///   合法性交给服务端判，422 的 message 显示在框内，改完可重试
  /// - start_at/end_at：`nullable|date`，留空即 null（create/update 都做 `?: null` 收口）
  /// - rollout_percent：`nullable|integer|between:0,100`
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'game.name', required: true),
    CrudField('type', 'game.type', type: CrudFieldType.select, required: true, editableOnEdit: false, options: <CrudOption>[
      CrudOption('signin', 'activity.type_signin'),
      CrudOption('daily_task', 'activity.type_daily_task'),
      CrudOption('invite', 'activity.type_invite'),
    ]),
    CrudField('status', 'game.status', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('0', 'activity.status_disabled'),
      CrudOption('1', 'activity.status_enabled'),
      CrudOption('2', 'activity.status_ended'),
    ]),
    CrudField('game_id', 'game.title', type: CrudFieldType.number, hint: 'activity.game_id_hint'),
    CrudField('start_at', 'activity.start_at', hint: 'activity.time_hint'),
    CrudField('end_at', 'activity.end_at', hint: 'activity.time_hint'),
    CrudField('rollout_percent', 'activity.rollout_percent', type: CrudFieldType.number, hint: 'activity.rollout_hint'),
    CrudField('config', 'activity.config', type: CrudFieldType.multiline, maxLines: 6, hint: 'activity.config_hint'),
  ];

  static const Map<String, String> _typeLabels = <String, String>{
    'signin': 'activity.type_signin',
    'daily_task': 'activity.type_daily_task',
    'invite': 'activity.type_invite',
  };

  static const Map<int, String> _statusLabels = <int, String>{
    0: 'activity.status_disabled',
    1: 'activity.status_enabled',
    2: 'activity.status_ended',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<ActivityAdminController>()) Get.put(ActivityAdminController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('activity.title')}",
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
            // 列表里的 id 是 hashid：{hashid} 路径用它
            final id = item['id']?.toString() ?? '';
            final name = item['name']?.toString() ?? '';
            final type = item['type']?.toString() ?? '';
            final status = item['status'] is int ? item['status'] as int : 0;
            return Card(child: ListTile(
              title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
              // 查不到的类型/状态（历史值）按原值展示，不硬塞成某一档
              subtitle: Text('${AppTranslations.t('game.type')}: ${AppTranslations.t(_typeLabels[type] ?? type)}  |  '
                  '${AppTranslations.t('game.status')}: ${AppTranslations.t(_statusLabels[status] ?? '$status')}  |  '
                  '${AppTranslations.t('activity.rollout_percent')}: ${item['rollout_percent']}%'),
              trailing: CrudRowActions(
                // 无开关：status 是三值枚举（含「已结束」），也没有 toggle 端点
                onEdit: () => _openForm(context, ctrl, item: item),
                onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
              ),
            ));
          },
        );
      })),
      const SizedBox(height: 8),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: ActivityAdminController.pageSize,
            onPage: (p) => ctrl.load(toPage: p),
          )),
    ]);
  }

  Future<void> _openForm(BuildContext context, ActivityAdminController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    if (item != null) {
      // config 列在模型上是 array cast（列表里已是对象），表单要的是 JSON 文本
      final config = item['config'];
      initial!['config'] = config == null ? '' : _encodeJson(config);
    }
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('activity.create')}' : '${AppTranslations.t('activity.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateActivity(item['id'].toString(), data),
    );
  }

  /// 列表回的对象（Map/List）转回 JSON 文本：Dart 的 toString() 出来是 `{rewards: [...]}`，
  /// 不是合法 JSON。数值走 jsonEncode 的默认序列化，金额在配置里本就是字符串（服务端 invalidReward 只认字符串/整数）。
  static String _encodeJson(dynamic value) => jsonEncode(value);
}
