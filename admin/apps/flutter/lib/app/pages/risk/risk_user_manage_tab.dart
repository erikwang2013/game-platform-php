// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 异常用户队列（user_trust）：列表 + 冻结 / 解冻 + 风控时间线。
// 端点（admin/app/admin/v1/controller/RiskUserController.php）：
//   GET  /admin/v1/risk/users?page=&size=（回 {total, items}；user_id 是 hashid）
//   POST /risk/users/{hashid}/hold                     —— **无 body**：冻结当前**全额**平台余额
//   POST /risk/users/{hashid}/release {amount?}        —— 金额是 bcmath 十进制串，服务端只做语法闸
//   GET  /risk/users/{hashid}/timeline                 —— risk_log/play_log/anticheat 合并，最多 200 条
// 金额一律 text 控件 + **原样透传字符串**（'100' 上送不能变成 100.0，服务端要按字符串 bcadd）。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskUserManageController extends GetxController {
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
      final resp = await api.get('/admin/v1/risk/users', params: <String, dynamic>{
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

  /// 冻结全额。返回 data.frozen_amount（bcmath 串，原样回显，不经过 double）。
  Future<String> hold(String hashid) async {
    final resp = await api.post('/admin/v1/risk/users/$hashid/hold');
    await load();
    return '${resp['data']['frozen_amount'] ?? ''}';
  }

  /// 解冻。amount 为空 = 全额（服务端对空值取 frozen 全量）；非空按**字符串**原样上送。
  Future<String> release(String hashid, String amount) async {
    final resp = await api.post(
      '/admin/v1/risk/users/$hashid/release',
      data: <String, dynamic>{if (amount.isNotEmpty) 'amount': amount},
    );
    await load();
    return '${resp['data']['released_amount'] ?? ''}';
  }

  Future<Map<String, dynamic>> timeline(String hashid) async {
    final resp = await api.get('/admin/v1/risk/users/$hashid/timeline');
    return (resp['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }
}

class RiskUserManageTab extends GetView<RiskUserManageController> {
  const RiskUserManageTab({super.key});

  /// 解冻金额：text 控件（服务端 bcadd((string)$raw,'0',8)，浮点控件会把 '100' 变成 100.0）。
  /// 留空 = 全额，故不必填。
  static const List<CrudField> _releaseFields = <CrudField>[
    CrudField('amount', 'risk.user.amount', hint: 'risk.user.amount_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskUserManageController>()) {
      Get.put(RiskUserManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(crudText('risk.tab_users'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.user.user'))),
                DataColumn(label: Text(crudText('risk.user.score'))),
                DataColumn(label: Text(crudText('risk.user.band'))),
                DataColumn(label: Text(crudText('risk.user.hits'))),
                DataColumn(label: Text(crudText('risk.user.last_hit'))),
                DataColumn(label: Text(crudText('risk.user.whitelisted'))),
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
            size: RiskUserManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
    ]);
  }

  DataRow _dataRow(BuildContext context, RiskUserManageController ctrl, dynamic row) {
    // 这一行**没有** id 键：服务端回的是 user_id（hashid），hold/release/timeline 都用它
    return DataRow(cells: [
      DataCell(Text(_name(row))),
      DataCell(Text('${row['score']}')),
      DataCell(Text(crudEnum('risk.band', row['band']))),
      DataCell(Text('${row['hit_count']}')),
      DataCell(Text('${row['last_hit_at']}')),
      DataCell(Text(row['whitelisted'] == 1 ? crudText('app.yes') : crudText('app.no'))),
      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(
          icon: const Icon(Icons.ac_unit, size: 18),
          tooltip: crudText('risk.user.hold'),
          onPressed: () => _hold(context, ctrl, row),
        ),
        IconButton(
          icon: const Icon(Icons.lock_open, size: 18),
          tooltip: crudText('risk.user.release'),
          onPressed: () => _release(context, ctrl, row),
        ),
        IconButton(
          icon: const Icon(Icons.timeline, size: 18),
          tooltip: crudText('risk.user.timeline'),
          onPressed: () => _timeline(context, ctrl, row),
        ),
      ])),
    ]);
  }

  /// 用户名可能为空（user 表查不到 ⇒ 服务端给「未知」）——确认文案里必须有个能认人的标识，
  /// 空名会让「确认冻结？」变成对任何一行都成立。退到 hashid。
  String _name(dynamic row) {
    final name = '${row['username'] ?? ''}'.trim();
    return name.isEmpty ? '${row['user_id']}' : name;
  }

  Future<void> _hold(BuildContext context, RiskUserManageController ctrl, dynamic row) async {
    final name = _name(row);
    await confirmCrudAction(
      context,
      title: '${crudText('app.confirm')} ${crudText('risk.user.hold')}',
      message: crudText('risk.user.hold_confirm', {'name': name}),
      confirmLabel: crudText('risk.user.hold'),
      onConfirm: () async {
        final amount = await ctrl.hold(row['user_id'].toString());
        Get.snackbar(crudText('app.success'), crudText('risk.user.hold_done', {'amount': amount}));
      },
    );
  }

  Future<void> _release(BuildContext context, RiskUserManageController ctrl, dynamic row) async {
    final name = _name(row);
    var released = '';
    final ok = await showCrudForm(
      context,
      title: crudText('risk.user.release_title', {'name': name}),
      fields: _releaseFields,
      onSubmit: (data) async {
        final amount = '${data['amount'] ?? ''}'.trim();
        // 金额原样上送：'100' 就是一个合法的 bcmath 串，前端不做数值化（浮点会把 0.1 变成 0.1000000000000000055）
        final done = await confirmCrudAction(
          context,
          title: '${crudText('app.confirm')} ${crudText('risk.user.release')}',
          message: crudText('risk.user.release_confirm', {
            'name': name,
            'amount': amount.isEmpty ? crudText('risk.user.release_all') : amount,
          }),
          confirmLabel: crudText('risk.user.release'),
          onConfirm: () async {
            released = await ctrl.release(row['user_id'].toString(), amount);
          },
        );
        if (!done) return; // 取消 ⇒ 一个字节都不发
      },
    );
    if (ok && released.isNotEmpty) {
      Get.snackbar(crudText('app.success'), crudText('risk.user.release_done', {'amount': released}));
    }
  }

  Future<void> _timeline(BuildContext context, RiskUserManageController ctrl, dynamic row) async {
    final Map<String, dynamic> data;
    try {
      data = await ctrl.timeline(row['user_id'].toString());
    } catch (e) {
      Get.snackbar(crudText('app.error'), apiErrorMessage(e));
      return;
    }
    final events = data['events'] as List<dynamic>? ?? [];
    Get.dialog(AlertDialog(
      title: Text(crudText('risk.user.timeline_title', {'name': _name(row)})),
      content: SizedBox(
        width: 720,
        height: 400,
        // 最多 200 条：限高自滚，别把弹框撑出屏幕
        child: events.isEmpty
            ? Center(child: Text(crudText('risk.user.timeline_empty')))
            : ListView.separated(
                itemCount: events.length,
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (_, index) {
                  final e = events[index];
                  return ListTile(
                    dense: true,
                    title: Text('${e['time']} · ${e['source']} · ${e['type']} ${e['action']}'),
                    subtitle: Text('${e['detail']}'),
                  );
                },
              ),
      ),
      actions: [TextButton(onPressed: () => Get.back(), child: Text(crudText('app.close')))],
    ));
  }
}
