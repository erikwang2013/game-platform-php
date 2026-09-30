// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class AnnouncementController extends GetxController {
  final api = ApiService();
  final announcements = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/announcement/list');
      announcements.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/announcement/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateAnnouncement(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/announcement/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/announcement/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 状态切换走独立端点（body 里 id 是 hashid），与 update 传 status 的不是同一条路
  Future<void> toggle(String hashid, int status) async {
    await api.post('/admin/v1/announcement/toggle', data: <String, dynamic>{'id': hashid, 'status': status});
    await load();
  }
}

class AnnouncementPage extends GetView<AnnouncementController> {
  const AnnouncementPage({super.key});

  /// 字段真值取自 AnnouncementController::create/update 的 validator + game_announcement 列定义：
  /// - type：**system/game/payment**（列注释 system=系统 game=游戏 payment=支付；
  ///   create 与 update 同口径，见 AnnouncementController.php:60 / :107）。
  ///   行里的值若在此之外（旧前端的 event/maintenance 造过这种行），底座会置顶原样显示且不提交，
  ///   不会被 in: 规则 422 拒掉、也不会被悄悄改写成第一项
  /// - target_lang：空串 = 全语言（列默认值），max 10
  /// - status：0 草稿 / 1 已发布
  /// - start_at/end_at 未纳入表单（要 datetime 控件；不传即不动这两列）
  static const List<CrudField> _fields = <CrudField>[
    CrudField('title', 'announcement.field_title', required: true),
    CrudField('content', 'announcement.content', type: CrudFieldType.multiline, required: true, maxLines: 5),
    CrudField('type', 'game.type', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('system', 'announcement.type_system'),
      CrudOption('game', 'announcement.type_game'),
      CrudOption('payment', 'announcement.type_payment'),
    ]),
    CrudField('target_lang', 'announcement.target_lang', hint: 'announcement.target_lang_hint'),
    CrudField('status', 'game.status', type: CrudFieldType.toggle),
  ];

  /// 列表类型列：查不到的类型（历史值）按原值展示，不硬塞成某一档
  static const Map<String, String> _typeLabels = <String, String>{
    'system': 'announcement.type_system',
    'game': 'announcement.type_game',
    'payment': 'announcement.type_payment',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AnnouncementController>()) {
      Get.put(AnnouncementController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('announcement.title')}",
          onCreate: () => _openForm(context, ctrl),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.announcements.isEmpty) {
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
                  DataColumn(label: Text('${AppTranslations.t('announcement.publish')}')),
                  DataColumn(label: Text("${AppTranslations.t('game.type')}")),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('announcement.publish_time')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.announcements.map((a) {
                  // 列表里的 id 是 hashid：{hashid} 路径与 toggle 的 id 都用它
                  final id = a['id']?.toString() ?? '';
                  final title = a['title']?.toString() ?? '';
                  final type = a['type']?.toString() ?? '';
                  final typeLabel = '${AppTranslations.t(_typeLabels[type] ?? type)}';
                  final status = a['status'] is int ? a['status'] as int : 0;
                  final createdAt = a['created_at']?.toString() ?? a['published_at']?.toString() ?? '';

                  return DataRow(cells: [
                    DataCell(Text(title)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Chip(
                      label: Text(status == 1 ? '${AppTranslations.t('announcement.published')}' : '${AppTranslations.t('announcement.draft')}'),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.grey.shade200),
                    )),
                    DataCell(Text(createdAt)),
                    DataCell(CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.toggle(id, next),
                      onEdit: () => _openForm(context, ctrl, item: a),
                      onDelete: () => confirmCrudDelete(context, what: title, onConfirm: () => ctrl.remove(id)),
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

  Future<void> _openForm(BuildContext context, AnnouncementController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('announcement.create')}' : '${AppTranslations.t('announcement.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateAnnouncement(item['id'].toString(), data),
    );
  }
}
