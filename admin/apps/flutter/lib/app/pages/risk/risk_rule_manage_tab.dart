// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 风控规则（管理页签）：risk_rule 的增 / 改 / 启停 / 沙箱试算。
// 端点（admin/app/admin/v1/controller/RiskRuleController.php）：
//   GET  /admin/v1/risk/rule/list?page=&size=  —— **分页参数是 size 不是 limit**；回 {total, items}
//   POST /risk/rule/create                     —— name/type/action 必填，config 是 JSON 字符串
//   PUT  /risk/rule/{hashid}                   —— **整单替换**：fill() 每次都重读 name/type/action
//   POST /risk/rule/{hashid}/toggle            —— **无 body**，服务端自己翻转（回 {status}）
//   POST /risk/rule/test {rule_id,user_id,check_type,context{}} —— 只读试算，不写库不落日志
// 规则没有 delete 端点（审计对象）⇒ 本页不摆删除。
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskRuleManageController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final total = 0.obs;
  final page = 1.obs;
  final isLoading = false.obs;

  /// 服务端 size 上限 100、缺省 20
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
      // 走 api.list：三种分页参数名一次发全（风控族读 `size`，但同族搜索/别处读 limit/per_page）
      final result = await api.list('/admin/v1/risk/rule/list', page: page.value, pageSize: pageSize);
      items.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar(crudText('app.error'), '${crudText('app.loading_failed')}: ${apiErrorMessage(e)}');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/risk/rule/create', data: data);
    await load();
  }

  /// 不叫 update：GetxController 自带 `update([ids, condition])`（触发重建），同名会撞成非法覆写
  Future<void> updateRule(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/risk/rule/$hashid', data: data);
    await load();
  }

  /// 无 body：服务端自己翻转。开关传进来的 next 只用于视觉，不参与请求。
  Future<void> toggle(String hashid) async {
    await api.post('/admin/v1/risk/rule/$hashid/toggle');
    await load();
  }

  Future<Map<String, dynamic>> test(Map<String, dynamic> data) async {
    final resp = await api.post('/admin/v1/risk/rule/test', data: data);
    return (resp['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }
}

class RiskRuleManageTab extends GetView<RiskRuleManageController> {
  const RiskRuleManageTab({super.key});

  /// 字段真值取自 RiskRuleController::fill/validateConfig：
  /// name/type/action 必填（create 与 update 都读），scope 缺省 all，config 是 JSON **字符串**，
  /// priority 夹到 0..1000，status 只认 0/1。值域外的 config 键服务端 422（键表见页脚说明）。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'risk.rule.name', required: true, hint: 'risk.rule.name_hint'),
    CrudField('type', 'risk.rule.type', type: CrudFieldType.select, required: true, hint: 'risk.rule.type_hint',
        options: <CrudOption>[
          CrudOption('ip_blacklist', 'risk.type.ip_blacklist'),
          CrudOption('amount_anomaly', 'risk.type.amount_anomaly'),
          CrudOption('frequency', 'risk.type.frequency'),
          CrudOption('velocity', 'risk.type.velocity'),
          CrudOption('device_fingerprint', 'risk.type.device_fingerprint'),
          CrudOption('ip_reputation', 'risk.type.ip_reputation'),
          CrudOption('device_account_graph', 'risk.type.device_account_graph'),
          CrudOption('withdraw_pattern', 'risk.type.withdraw_pattern'),
        ]),
    CrudField('action', 'risk.rule.action', type: CrudFieldType.select, required: true,
        options: <CrudOption>[
          CrudOption('log', 'risk.action.log'),
          CrudOption('warn', 'risk.action.warn'),
          CrudOption('block', 'risk.action.block'),
        ]),
    CrudField('scope', 'risk.rule.scope', type: CrudFieldType.select, options: <CrudOption>[
      CrudOption('all', 'risk.scope.all'),
      CrudOption('deposit', 'risk.scope.deposit'),
      CrudOption('withdraw', 'risk.scope.withdraw'),
      CrudOption('exchange', 'risk.scope.exchange'),
      CrudOption('login', 'risk.scope.login'),
    ]),
    CrudField('config', 'risk.rule.config', type: CrudFieldType.multiline, hint: 'risk.rule.config_hint'),
    CrudField('priority', 'risk.rule.priority', type: CrudFieldType.number, hint: 'risk.rule.priority_hint'),
    CrudField('status', 'risk.rule.status', type: CrudFieldType.toggle),
  ];

  /// 试算参数：user_id 必填（控制器 decodeId('') 会 400，注释里的「0=未登录」走不通）；
  /// check_type 缺省 login；context 是**对象**不是字符串（服务端对非数组静默兜成 []）。
  static const List<CrudField> _testFields = <CrudField>[
    CrudField('user_id', 'risk.rule.test_user', required: true, hint: 'risk.rule.test_user_hint'),
    CrudField('check_type', 'risk.rule.test_check_type', type: CrudFieldType.select, options: <CrudOption>[
      CrudOption('login', 'risk.scope.login'),
      CrudOption('deposit', 'risk.scope.deposit'),
      CrudOption('withdraw', 'risk.scope.withdraw'),
      CrudOption('exchange', 'risk.scope.exchange'),
    ]),
    CrudField('context', 'risk.rule.test_context', type: CrudFieldType.multiline, hint: 'risk.rule.test_context_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskRuleManageController>()) {
      Get.put(RiskRuleManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(title: crudText('risk.tab_rules'), onCreate: () => _openForm(context, ctrl, null)),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.rule.name'))),
                DataColumn(label: Text(crudText('risk.rule.type'))),
                DataColumn(label: Text(crudText('risk.rule.action'))),
                DataColumn(label: Text(crudText('risk.rule.scope'))),
                DataColumn(label: Text(crudText('risk.rule.config'))),
                DataColumn(label: Text(crudText('risk.rule.priority'))),
                DataColumn(label: Text(crudText('risk.rule.status'))),
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
            size: RiskRuleManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
      // 长说明限高自滚：表格矮的时候不许把页脚顶出屏幕
      ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 96),
        child: SingleChildScrollView(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(crudText('risk.rule.no_delete'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
            Text(crudText('risk.rule.keys_note'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
          ]),
        ),
      ),
    ]);
  }

  DataRow _dataRow(BuildContext context, RiskRuleManageController ctrl, dynamic row) {
    final id = row['id'].toString();
    final on = (row['status'] as num?)?.toInt() == 1;

    return DataRow(cells: [
      DataCell(Text('${row['name']}')),
      DataCell(Text(crudEnum('risk.type', row['type']))),
      DataCell(Text(crudEnum('risk.action', row['action']))),
      DataCell(Text(crudEnum('risk.scope', row['scope']))),
      DataCell(Text('${row['config']}')),
      DataCell(Text('${row['priority']}')),
      DataCell(Text(on ? crudText('app.enabled') : crudText('app.disabled'))),
      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
        CrudRowActions(
          status: (row['status'] as num?)?.toInt() ?? 0,
          // 无 body 的翻转：服务端自己取反，客户端不传目标状态（传了也没人读）
          onToggle: (_) => ctrl.toggle(id),
          onEdit: () => _openForm(context, ctrl, row),
        ),
        IconButton(
          icon: const Icon(Icons.play_circle_outline, size: 18),
          tooltip: crudText('risk.rule.test'),
          onPressed: () => _openTest(context, ctrl, row),
        ),
      ])),
    ]);
  }

  Future<void> _openForm(BuildContext context, RiskRuleManageController ctrl, dynamic row) async {
    final editing = row != null;
    final ok = await showCrudForm(
      context,
      title: editing ? crudText('risk.rule.edit') : crudText('risk.rule.create'),
      fields: _fields,
      initial: editing ? Map<String, dynamic>.from(row as Map) : null,
      // Trap A：update 复用 create 的 fill()，name/type/action 一律从请求体读且必填
      // ⇒ 编辑态必须整份提交，「只发改动字段」会 422。
      fullEdit: true,
      onSubmit: (data) async {
        if (editing) {
          await ctrl.updateRule(row['id'].toString(), data);
        } else {
          await ctrl.create(data);
        }
      },
    );
    if (ok) Get.snackbar(crudText('app.success'), crudText('app.saved'));
  }

  Future<void> _openTest(BuildContext context, RiskRuleManageController ctrl, dynamic row) async {
    var result = <String, dynamic>{};
    final ok = await showCrudForm(
      context,
      title: crudText('risk.rule.test_title', {'name': '${row['name']}'}),
      fields: _testFields,
      onSubmit: (data) async {
        final raw = (data['context'] as String? ?? '').trim();
        if (raw.isEmpty) {
          // 空 = 不带上下文（服务端对非数组一律兜成 []，不发也不会误判）
          data.remove('context');
        } else {
          try {
            final decoded = jsonDecode(raw);
            // 服务端只认数组：发字符串会被静默兜成 [] ⇒ 试算结果看着「没命中」其实上下文全丢了
            if (decoded is! Map) throw ApiException(0, crudText('risk.rule.test_context_object'));
            data['context'] = decoded.cast<String, dynamic>();
          } on FormatException {
            throw ApiException(0, crudText('risk.rule.test_context_object'));
          }
        }
        data.removeWhere((key, value) => value == '' && key != 'context');
        data['rule_id'] = row['id'].toString();
        result = await ctrl.test(data);
      },
    );
    if (!ok) return;
    Get.dialog(AlertDialog(
      title: Text(crudText('risk.rule.test_done')),
      content: Text(crudText('risk.rule.test_result', {
        'matched': '${result['matched']}',
        'severity': '${result['severity']}',
        'action': '${result['action']}',
        'message': '${result['message']}',
      })),
      actions: [
        TextButton(onPressed: () => Get.back(), child: Text(crudText('app.close'))),
      ],
    ));
  }
}
