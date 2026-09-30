// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

/// 游戏区服：**列表端点按 game_id 过滤且 game_id 必填**（GameServerController::list 的 validator），
/// 所以页面先选游戏再列区服。
class GameServerAdminController extends GetxController {
  final api = ApiService();
  final games = <dynamic>[].obs;
  final gameId = RxnString();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;
  final isLoadingGames = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadGames();
  }

  Future<void> loadGames() async {
    isLoadingGames.value = true;
    try {
      // 游戏列表默认 15 条/页，选择器要的是全集 ⇒ 显式放大 limit（服务端无上限，200 只是 UI 侧的理智界）
      final resp = await api.get('/admin/v1/game/list', params: <String, dynamic>{'limit': 200});
      games.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
      if (games.isNotEmpty) {
        // 默认落在第一个游戏上：否则页面打开是空的，用户得先点一下才有东西看
        await selectGame(games.first['id']?.toString());
      }
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoadingGames.value = false;
    }
  }

  Future<void> selectGame(String? hashid) async {
    gameId.value = hashid;
    items.clear();
    if (hashid != null) await load();
  }

  Future<void> load() async {
    final gid = gameId.value;
    if (gid == null) return;
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/game/server/list', params: <String, dynamic>{'game_id': gid});
      items.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/game/server/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateServer(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/game/server/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/game/server/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class GameServerPage extends GetView<GameServerAdminController> {
  const GameServerPage({super.key});

  /// 字符真值取自 GameServerController 的 validator + game_game_server 列定义：
  /// - game_id：create 必填（hashid 字符串），**update 的规则里没有** ⇒ 编辑态置灰且不提交
  /// - name：max 50（create/update 同口径）
  /// - region：max 20，列注释是 global/asia/eu/na
  /// - status：**四值枚举**（0=维护 1=正常 2=火爆 3=新服，见列注释，create 的默认值 1 只是其一）
  ///   ⇒ 用 select 而不是开关；本模块也没有 toggle 端点，状态只在表单里改
  /// - sort：列是 INT UNSIGNED ⇒ min:0
  static const List<CrudOption> _statusOptions = <CrudOption>[
    CrudOption('0', 'game_server.status_maintenance'),
    CrudOption('1', 'game_server.status_normal'),
    CrudOption('2', 'game_server.status_hot'),
    CrudOption('3', 'game_server.status_new'),
  ];

  /// 选项 label 照传 i18n key；游戏名是数据不是 key，crudText 查不到就回退成原值（底座已注明）
  List<CrudField> _fields(GameServerAdminController ctrl) => <CrudField>[
        CrudField(
          'game_id',
          'game.title',
          type: CrudFieldType.select,
          required: true,
          editableOnEdit: false,
          options: <CrudOption>[
            for (final g in ctrl.games) CrudOption(g['id']?.toString() ?? '', g['name']?.toString() ?? ''),
          ],
        ),
        CrudField('name', 'game.name', required: true),
        CrudField('region', 'game.region', hint: 'game_server.region_hint'),
        CrudField('status', 'game.status', type: CrudFieldType.select, required: true, options: _statusOptions),
        CrudField('sort', 'game.sort', type: CrudFieldType.number),
      ];

  static const Map<int, String> _statusLabels = <int, String>{
    0: 'game_server.status_maintenance',
    1: 'game_server.status_normal',
    2: 'game_server.status_hot',
    3: 'game_server.status_new',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<GameServerAdminController>()) {
      Get.put(GameServerAdminController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('game_server.title')}",
          onCreate: () => _openForm(context, ctrl),
        ),
        const SizedBox(height: 12),
        // 游戏选择器。只在游戏加载完之后才首次构建：DropdownButtonFormField 的 initialValue
        // 只在 initState 生效，先建后填列表会让它显示成"没选中"而表格里却有数据。
        Obx(() {
          if (ctrl.isLoadingGames.value) {
            return const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: LinearProgressIndicator());
          }
          if (ctrl.games.isEmpty) return const SizedBox.shrink();
          return DropdownButtonFormField<String>(
            initialValue: ctrl.gameId.value,
            decoration: InputDecoration(labelText: crudText('game.title')),
            items: <DropdownMenuItem<String>>[
              for (final g in ctrl.games)
                DropdownMenuItem(value: g['id']?.toString() ?? '', child: Text(g['name']?.toString() ?? '')),
            ],
            onChanged: (value) => ctrl.selectGame(value),
          );
        }),
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
                  DataColumn(label: Text("${AppTranslations.t('game.region')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.sort')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.items.map((s) {
                  // 列表里的 id 是 hashid：{hashid} 路径用它
                  final id = s['id']?.toString() ?? '';
                  final name = s['name']?.toString() ?? '';
                  final region = s['region']?.toString() ?? '';
                  final sort = s['sort']?.toString() ?? '0';
                  final status = s['status'] is int ? s['status'] as int : 0;

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    DataCell(Text(region)),
                    DataCell(Chip(
                      label: Text('${AppTranslations.t(_statusLabels[status] ?? '$status')}'),
                      // 0=维护（红）、2=火爆（橙），1/3 常规
                      color: WidgetStatePropertyAll(
                        status == 0 ? Colors.red.shade50 : (status == 2 ? Colors.orange.shade50 : Colors.green.shade50),
                      ),
                    )),
                    DataCell(Text(sort)),
                    DataCell(CrudRowActions(
                      // 无 status 开关：状态是四值枚举，Switch 只能表达两态；
                      // 也没有 toggle 端点（路由表只有 list/create/update/delete）
                      onEdit: () => _openForm(context, ctrl, item: s),
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

  Future<void> _openForm(BuildContext context, GameServerAdminController ctrl, {dynamic item}) {
    final initial = item == null
        ? <String, dynamic>{'game_id': ctrl.gameId.value}
        : Map<String, dynamic>.from(item as Map);
    if (item != null) {
      // 行里的 game_id 是**原始整数**（encodeIds 只编 `id` 这一列），拿去当 select 值会显示成一串数字；
      // 当前筛选的游戏 hashid 页面本来就知道，直接顶上（该字段编辑态置灰、也不提交）
      initial['game_id'] = ctrl.gameId.value;
    }
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('game_server.create')}' : '${AppTranslations.t('game_server.edit')}',
      fields: _fields(ctrl),
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateServer(item['id'].toString(), data),
    );
  }
}
