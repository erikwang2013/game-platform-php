// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 风控事件（risk_log）：只读列表 + 人工处置。
// 端点（admin/app/admin/v1/controller/RiskEventController.php）：
//   GET  /admin/v1/risk/event/list?page=&size=（分页参数 size；回 {total, items}，行已脱敏 ip/fp）
//   POST /risk/event/{hashid}/handle {decision: approve|reject, note ≤500}
// **handle 不写 risk_log**（risk_log 没有审核状态列）：控制器只回一句「已记录人工处置」，
// 留痕落在 OperationLog 中间件。故按钮对**所有行**都出现——没有状态机可依，不是漏判。
// 驳回是危险动作 ⇒ 二次确认，文案带规则名与命中时间。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskEventManageController extends GetxController {
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
      final resp = await api.get('/admin/v1/risk/event/list', params: <String, dynamic>{
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

  /// 返回服务端 message（「已记录人工处置（操作审计可查）」）
  Future<String> handle(String hashid, String decision, String note) async {
    final resp = await api.post('/admin/v1/risk/event/$hashid/handle', data: <String, dynamic>{
      'decision': decision,
      'note': note,
    });
    await load();
    return resp['message']?.toString() ?? '';
  }
}

class RiskEventManageTab extends GetView<RiskEventManageController> {
  const RiskEventManageTab({super.key});

  /// 结论是 select ⇒ 底座在无初值时取第一项（approve），与提现审核同款；
  /// 选 reject 会走二次确认。note 服务端截到 500 字。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('decision', 'risk.event.decision', type: CrudFieldType.select, required: true,
        hint: 'risk.event.decision_hint',
        options: <CrudOption>[
          CrudOption('approve', 'risk.event.approve'),
          CrudOption('reject', 'risk.event.reject'),
        ]),
    CrudField('note', 'risk.event.note', type: CrudFieldType.multiline, hint: 'risk.event.note_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskEventManageController>()) {
      Get.put(RiskEventManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(crudText('risk.tab_events'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
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
                DataColumn(label: Text(crudText('risk.event.rule'))),
                DataColumn(label: Text(crudText('risk.event.user'))),
                DataColumn(label: Text(crudText('risk.rule.type'))),
                DataColumn(label: Text(crudText('risk.rule.action'))),
                DataColumn(label: Text(crudText('risk.event.result'))),
                DataColumn(label: Text(crudText('risk.actions'))),
              ],
              rows: [
                for (final row in ctrl.items)
                  DataRow(cells: [
                    DataCell(Text('${row['created_at']}')),
                    DataCell(Text(_ruleLabel(row))),
                    DataCell(Text('${row['user_id']}')),
                    DataCell(Text(crudEnum('risk.type', row['type']))),
                    DataCell(Text(crudEnum('risk.action', row['action']))),
                    DataCell(Text('${row['result']}')),
                    DataCell(IconButton(
                      icon: const Icon(Icons.gavel_outlined, size: 18),
                      tooltip: crudText('risk.event.handle'),
                      onPressed: () => _openHandle(context, ctrl, row),
                    )),
                  ]),
              ],
            ),
          ),
        );
      })),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: RiskEventManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
    ]);
  }

  /// 规则名可能为空（规则被删/rule_id=0 的人工冻结事件）⇒ 退回类型，别显示空白
  String _ruleLabel(dynamic row) {
    final name = '${row['rule_name'] ?? ''}'.trim();
    return name.isEmpty ? '${row['type']}' : name;
  }

  /// 二次确认文案里的对象标识：规则名 + 命中时间（同一规则会有一堆事件，光有名字对不上行）
  String _who(dynamic row) => '${_ruleLabel(row)} @ ${row['created_at']}';

  Future<void> _openHandle(BuildContext context, RiskEventManageController ctrl, dynamic row) async {
    var message = '';
    final ok = await showCrudForm(
      context,
      title: crudText('risk.event.handle_title', {'name': _ruleLabel(row)}),
      fields: _fields,
      onSubmit: (data) async {
        final decision = '${data['decision']}';
        // 驳回 = 判为误判，是本次处置里唯一带否定的结论 ⇒ 发出去之前再问一句
        if (decision == 'reject') {
          final confirmed = await confirmCrudAction(
            context,
            title: '${crudText('app.confirm')} ${crudText('risk.event.handle')}',
            message: crudText('risk.event.reject_confirm', {'name': _who(row)}),
            confirmLabel: crudText('risk.event.handle'),
            onConfirm: () async {
              message = await ctrl.handle(row['id'].toString(), decision, '${data['note'] ?? ''}');
            },
          );
          if (!confirmed) return; // 取消 ⇒ 一个字节都不发
          return;
        }
        message = await ctrl.handle(row['id'].toString(), decision, '${data['note'] ?? ''}');
      },
    );
    if (ok && message.isNotEmpty) Get.snackbar(crudText('app.success'), message);
  }
}
