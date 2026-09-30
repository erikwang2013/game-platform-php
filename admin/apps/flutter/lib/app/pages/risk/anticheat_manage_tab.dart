// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 反作弊事件（anticheat_event）：列表 + 证据查看 + 人工审核。
// 端点（admin/app/admin/v1/controller/AntiCheatController.php）：
//   GET  /admin/v1/anticheat/events?page=&size=（回 {total, items}，行里带 status/review_note）
//   POST /anticheat/events/{hashid}/review {status, note}  —— status 是**字符串枚举**
//        open/confirmed/whitelisted/closed（不是 0/1 翻转），note 服务端截 255
// 审核只写事件行（控制器注释：当前仅记事件，不联动信任分）⇒ 不改用户资产，重判随时可推翻。
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class AntiCheatManageController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final total = 0.obs;
  final page = 1.obs;
  final isLoading = false.obs;

  static const int pageSize = 20;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/anticheat/events', params: <String, dynamic>{
        'page': page.value,
        'size': pageSize,
      });
      items.value = resp['data']['items'] as List<dynamic>? ?? [];
      total.value = (resp['data']['total'] as num?)?.toInt() ?? 0;
    } catch (e) {
      Get.snackbar(crudText('app.error'), '${crudText('app.loading_failed')}: ${apiErrorMessage(e)}');
    } finally {
      isLoading.value = false;
    }
  }

  /// 返回服务端 message（'审核已记录'）。
  Future<String> review(String hashid, String status, String note) async {
    final resp = await api.post('/admin/v1/anticheat/events/$hashid/review', data: <String, dynamic>{
      'status': status,
      'note': note,
    });
    await load();
    return '${resp['data']['message'] ?? ''}';
  }
}

class AntiCheatManageTab extends GetView<AntiCheatManageController> {
  const AntiCheatManageTab({super.key});

  /// status 是**字符串枚举**：open/confirmed/whitelisted/closed。select 无初值时取第一项
  /// （open = 待处理，等于「没结论」），要下别的结论必须显式选。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('status', 'risk.ac.status', type: CrudFieldType.select, required: true,
        options: <CrudOption>[
          CrudOption('open', 'risk.ac.status.open'),
          CrudOption('confirmed', 'risk.ac.status.confirmed'),
          CrudOption('whitelisted', 'risk.ac.status.whitelisted'),
          CrudOption('closed', 'risk.ac.status.closed'),
        ]),
    CrudField('note', 'risk.ac.note', type: CrudFieldType.multiline, hint: 'risk.ac.note_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AntiCheatManageController>()) {
      Get.put(AntiCheatManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(crudText('risk.tab_anticheat'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.user.event_time'))),
                DataColumn(label: Text(crudText('risk.ac.rule'))),
                DataColumn(label: Text(crudText('risk.ac.user'))),
                DataColumn(label: Text(crudText('risk.ac.severity'))),
                DataColumn(label: Text(crudText('risk.ac.score_delta'))),
                DataColumn(label: Text(crudText('risk.ac.status'))),
                DataColumn(label: Text(crudText('risk.actions'))),
              ],
              rows: [for (final row in ctrl.items) _dataRow(context, ctrl, row)],
            ),
          ),
        );
      })),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: AntiCheatManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
    ]);
  }

  DataRow _dataRow(BuildContext context, AntiCheatManageController ctrl, dynamic row) {
    return DataRow(cells: [
      DataCell(Text('${row['created_at']}')),
      DataCell(Text(_ruleLabel(row))),
      DataCell(Text('${row['user_id']}')),
      DataCell(Text('${row['severity']}')),
      DataCell(Text('${row['score_delta']}')),
      DataCell(Text(crudEnum('risk.ac.status', row['status']))),
      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(
          icon: const Icon(Icons.article_outlined, size: 18),
          tooltip: crudText('risk.ac.evidence'),
          onPressed: () => _evidence(context, row),
        ),
        IconButton(
          icon: const Icon(Icons.rate_review_outlined, size: 18),
          tooltip: crudText('risk.ac.review'),
          onPressed: () => _review(context, ctrl, row),
        ),
      ])),
    ]);
  }

  /// 规则名可能为空（改名/删规则后）⇒ 退回 rule_type，别显示空白
  String _ruleLabel(dynamic row) {
    final name = '${row['rule_name'] ?? ''}'.trim();
    return name.isEmpty ? '${row['rule_type']}' : name;
  }

  /// 审的是「哪一条命中」：规则名 + 时间（同一规则一天能出几百条）
  String _who(dynamic row) => '${_ruleLabel(row)} @ ${row['created_at']}';

  /// 证据是审核的唯一依据：不给看原文就只能靠猜（evidence 是对象，逐行铺开）
  Future<void> _evidence(BuildContext context, dynamic row) async {
    final evidence = row['evidence'];
    final lines = <String>[
      for (final entry in (evidence is Map ? evidence : const <dynamic, dynamic>{}).entries)
        '${entry.key}: ${entry.value}',
    ];
    Get.dialog(AlertDialog(
      title: Text(crudText('risk.ac.evidence')),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: SelectableText(lines.isEmpty ? jsonEncode(evidence) : lines.join('\n')),
        ),
      ),
      actions: [TextButton(onPressed: () => Get.back(), child: Text(crudText('app.close')))],
    ));
  }

  Future<void> _review(BuildContext context, AntiCheatManageController ctrl, dynamic row) async {
    var message = '';
    final ok = await showCrudForm(
      context,
      title: crudText('risk.ac.review_title', {'name': _ruleLabel(row)}),
      fields: _fields,
      initial: <String, dynamic>{'status': '${row['status']}', 'note': '${row['review_note'] ?? ''}'},
      onSubmit: (data) async {
        final status = '${data['status']}';
        final note = '${data['note'] ?? ''}';
        // 加白 = 判定玩家没问题，是四个结论里唯一「否定指控」的那个 ⇒ 发出去之前再问一句
        if (status == 'whitelisted') {
          final done = await confirmCrudAction(
            context,
            title: '${crudText('app.confirm')} ${crudText('risk.ac.review')}',
            message: crudText('risk.ac.whitelist_confirm', {'name': _who(row)}),
            confirmLabel: crudText('risk.ac.status.whitelisted'),
            onConfirm: () async {
              message = await ctrl.review(row['id'].toString(), status, note);
            },
          );
          if (!done) return; // 取消 ⇒ 一个字节都不发
          return;
        }
        message = await ctrl.review(row['id'].toString(), status, note);
      },
    );
    if (ok && message.isNotEmpty) Get.snackbar(crudText('app.success'), message);
  }
}
