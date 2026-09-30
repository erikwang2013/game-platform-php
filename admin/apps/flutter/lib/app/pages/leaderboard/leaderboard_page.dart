// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class LeaderboardAdminController extends GetxController {
  final api = ApiService();
  final games = <dynamic>[].obs;
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
    // 新建表单要挑游戏（game_id 是 hashid），先备着
    loadGames();
  }

  Future<void> loadGames() async {
    try {
      // 游戏列表默认 15 条/页，选择器要全集 ⇒ 放大 limit（服务端无上限，200 只是 UI 侧的理智界）
      final resp = await api.get('/admin/v1/game/list', params: <String, dynamic>{'limit': 200});
      games.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    }
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/leaderboard/list');
      items.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/leaderboard/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateBoard(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/leaderboard/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/leaderboard/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 排行榜没有 toggle 端点：状态走 update 的局部 PUT（status 规则 in:0,1）。
  /// 行内开关不弹「已保存」——开关本身就是反馈，失败时 CrudRowActions 会弹服务端 message。
  Future<void> updateStatus(String hashid, int status) async {
    await api.put('/admin/v1/leaderboard/$hashid', data: <String, dynamic>{'status': status});
    await load();
  }

  /// 重算缓存（POST {hashid}/refresh）没有表单承接异常，就地捕获后弹提示；
  /// 失败即列表维持原样，只有服务端 message 告诉用户为什么。
  /// 名字不能叫 refresh：GetxController 自己带一个 `refresh()`（ChangeNotifier 那套），会撞。
  Future<void> refreshCache(String hashid) async {
    try {
      await api.post('/admin/v1/leaderboard/$hashid/refresh');
      await load();
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('leaderboard.refreshed')}');
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', apiErrorMessage(e));
    }
  }
}

class LeaderboardPage extends GetView<LeaderboardAdminController> {
  const LeaderboardPage({super.key});

  /// 字段真值取自 LeaderboardController::create/update 的 validator + game_leaderboard 列定义：
  /// - type：daily/weekly/monthly/alltime（列注释与 update 的枚举一致；未知值走全时段分支）
  /// - metric：earned/spent/play_count（列注释里还写着 level，但两个 validator 都不收 ⇒ 按 validator）
  /// - game_id：**create 收 hashid**（decodeId 解），update 的规则里没有 ⇒ 编辑态置灰且不提交；
  ///   留空 = 全平台（0）。列表回的是**原始整数** game_id（encodeIds 只编 `id`），选不中任何选项，
  ///   底座会置顶原样显示它且不提交——编辑态看到那串数字就是游戏原始 id，不是 bug，也改不动它。
  /// - rule：TEXT，create 不校验、update `nullable|string`，内容由 LeaderboardService 解释
  static const List<CrudField> _tailFields = <CrudField>[
    CrudField('status', 'game.status', type: CrudFieldType.toggle),
    CrudField('sort', 'game.sort', type: CrudFieldType.number),
  ];

  List<CrudField> _fields(LeaderboardAdminController ctrl) => <CrudField>[
        CrudField('name', 'game.name', required: true),
        CrudField('type', 'leaderboard.type', type: CrudFieldType.select, required: true, options: <CrudOption>[
          CrudOption('daily', 'leaderboard.type_daily'),
          CrudOption('weekly', 'leaderboard.type_weekly'),
          CrudOption('monthly', 'leaderboard.type_monthly'),
          CrudOption('alltime', 'leaderboard.type_alltime'),
        ]),
        CrudField('metric', 'leaderboard.metric', type: CrudFieldType.select, required: true, options: <CrudOption>[
          CrudOption('earned', 'leaderboard.metric_earned'),
          CrudOption('spent', 'leaderboard.metric_spent'),
          CrudOption('play_count', 'leaderboard.metric_play_count'),
        ]),
        CrudField(
          'game_id',
          'game.title',
          type: CrudFieldType.select,
          editableOnEdit: false,
          options: <CrudOption>[
            const CrudOption('', 'leaderboard.all_platforms'),
            for (final g in ctrl.games) CrudOption(g['id']?.toString() ?? '', g['name']?.toString() ?? ''),
          ],
        ),
        CrudField('rule', 'leaderboard.rule', type: CrudFieldType.multiline, hint: 'leaderboard.rule_hint'),
        ..._tailFields,
      ];

  static const Map<String, String> _typeLabels = <String, String>{
    'daily': 'leaderboard.type_daily',
    'weekly': 'leaderboard.type_weekly',
    'monthly': 'leaderboard.type_monthly',
    'alltime': 'leaderboard.type_alltime',
  };

  static const Map<String, String> _metricLabels = <String, String>{
    'earned': 'leaderboard.metric_earned',
    'spent': 'leaderboard.metric_spent',
    'play_count': 'leaderboard.metric_play_count',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<LeaderboardAdminController>()) {
      Get.put(LeaderboardAdminController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('leaderboard.title')}",
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
                  DataColumn(label: Text("${AppTranslations.t('leaderboard.type')}")),
                  DataColumn(label: Text("${AppTranslations.t('leaderboard.metric')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.sort')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.items.map((b) {
                  // 列表里的 id 是 hashid：{hashid} 路径与 refresh 的路径都用它
                  final id = b['id']?.toString() ?? '';
                  final name = b['name']?.toString() ?? '';
                  final type = b['type']?.toString() ?? '';
                  final metric = b['metric']?.toString() ?? '';
                  final sort = b['sort']?.toString() ?? '0';
                  final status = b['status'] is int ? b['status'] as int : 0;

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    // 查不到的类型（历史值）按原值展示，不硬塞成某一档
                    DataCell(Chip(label: Text('${AppTranslations.t(_typeLabels[type] ?? type)}'))),
                    DataCell(Chip(label: Text('${AppTranslations.t(_metricLabels[metric] ?? metric)}'))),
                    DataCell(Text(sort)),
                    DataCell(Chip(
                      label: Text(status == 1 ? '${AppTranslations.t('app.enabled')}' : '${AppTranslations.t('app.disabled')}'),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.updateStatus(id, next),
                      onRefresh: () => ctrl.refreshCache(id),
                      onEdit: () => _openForm(context, ctrl, item: b),
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

  Future<void> _openForm(BuildContext context, LeaderboardAdminController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('leaderboard.create')}' : '${AppTranslations.t('leaderboard.edit')}',
      fields: _fields(ctrl),
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateBoard(item['id'].toString(), data),
    );
  }
}
