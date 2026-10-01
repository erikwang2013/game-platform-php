// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 团伙（risk_cluster）：列表 + 聚类检测 + 人工登记 + 三值状态 + 成员。
// 端点（admin/app/admin/v1/controller/RiskClusterController.php）：
//   GET  /admin/v1/risk/clusters?page=&size=（**没有 /list 段**；行里带 fingerprint 原文）
//   POST /risk/clusters/detect            —— 全局动作（不属于任何一行），**只返回候选不落库**
//   POST /risk/clusters/confirm           —— {type, fingerprint, name}；member_ids 见下
//   PUT  /risk/clusters/{hashid}/status   —— {status: 0|1|2}（不是 0/1 翻转）
//   GET  /risk/clusters/{hashid}/members  —— 回 [{id: hashid, username}]，按依据值由服务端解析
// confirm 的 member_ids 现在**收 hashid**（服务端逐个 decodeId，非法值 400 fail-fast），
// 但本页仍然**不上送**：候选是 detect 的输出，只有 type/fingerprint/masked/user_count，
// 成员的 hashid 只能对**已落库**的团伙从 members 端点取——建团这一刻手里根本没有 hashid。
// 故走指纹回填（服务端 resolveMemberIds 按 fingerprint 解析），这不是「上送会被吃成 0」那条旧理由。
// 已知并接受：`manual` 与 `same_pay_account` 没有可回填的依据值，又不发 member_ids
// ⇒ 这两类团伙的成员列表**永远为空**（只有 user_count 是运营填的那个数）。不给它们开成员选择：
// 要么让前端自己造一份成员真值（必然与服务端漂移），要么让运营手抄 hashid（抄错即 400）。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskClusterManageController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final total = 0.obs;
  final page = 1.obs;
  final isLoading = false.obs;

  /// detect 的候选（内存态，不落库）：null = 面板没展开过，[] = 展开了但没候选
  final candidates = Rxn<List<dynamic>>();
  final windowDays = 7.obs;

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
      final result = await api.list('/admin/v1/risk/clusters', page: page.value, pageSize: pageSize);
      items.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar(crudText('app.error'), '${crudText('app.loading_failed')}: ${apiErrorMessage(e)}');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> detect() async {
    final resp = await api.post('/admin/v1/risk/clusters/detect');
    windowDays.value = (resp['data']['window_days'] as num?)?.toInt() ?? 7;
    candidates.value = resp['data']['candidates'] as List<dynamic>? ?? <dynamic>[];
  }

  Future<void> confirm(Map<String, dynamic> data) async {
    await api.post('/admin/v1/risk/clusters/confirm', data: data);
    candidates.value = null; // 候选列表已过时（新确认的团伙可能正来自其中一条）
    await load();
  }

  Future<void> setStatus(String hashid, int status) async {
    await api.put('/admin/v1/risk/clusters/$hashid/status', data: <String, dynamic>{'status': status});
    await load();
  }

  Future<Map<String, dynamic>> members(String hashid) async {
    final resp = await api.get('/admin/v1/risk/clusters/$hashid/members');
    return (resp['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
  }
}

class RiskClusterManageTab extends GetView<RiskClusterManageController> {
  const RiskClusterManageTab({super.key});

  /// status 是**三值**（0 误判 / 1 观察中 / 2 已处置），不是启停开关 ⇒ 用菜单不用 Switch。
  static const List<int> _statuses = <int>[0, 1, 2];

  /// 建团：name 必填（服务端截 100）；type ∈ same_ip/same_device/same_pay_account/manual；
  /// same_ip/same_device **必须**带 fingerprint（服务端 422），manual 可空。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'risk.cluster.name', required: true),
    CrudField('type', 'risk.cluster.type', type: CrudFieldType.select, required: true,
        hint: 'risk.cluster.type_hint',
        options: <CrudOption>[
          CrudOption('same_ip', 'risk.cluster.type.same_ip'),
          CrudOption('same_device', 'risk.cluster.type.same_device'),
          CrudOption('same_pay_account', 'risk.cluster.type.same_pay_account'),
          CrudOption('manual', 'risk.cluster.type.manual'),
        ]),
    CrudField('fingerprint', 'risk.cluster.fingerprint', hint: 'risk.cluster.fingerprint_hint'),
    // 列表有这一列，而服务端**收**这个字段（`max(count(member_ids), user_count)`）⇒ 不摆就是恒 0 的假列。
    // 不负责精度：本页不发 member_ids，服务端算得出更准的值时以它为准（hint 里写了）。
    CrudField('user_count', 'risk.cluster.user_count', type: CrudFieldType.number,
        hint: 'risk.cluster.user_count_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskClusterManageController>()) {
      Get.put(RiskClusterManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text(crudText('risk.tab_clusters'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        OutlinedButton.icon(
          icon: const Icon(Icons.hub_outlined, size: 18),
          label: Text(crudText('risk.cluster.detect')),
          onPressed: () => _detect(context, ctrl),
        ),
        const SizedBox(width: 8),
        ElevatedButton.icon(
          icon: const Icon(Icons.add),
          label: Text(crudText('risk.cluster.manual')),
          onPressed: () => _openForm(context, ctrl, null),
        ),
      ]),
      Obx(() {
        final list = ctrl.candidates.value;
        if (list == null) return const SizedBox.shrink();
        return _candidatePanel(context, ctrl, list);
      }),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.cluster.name'))),
                DataColumn(label: Text(crudText('risk.cluster.type'))),
                DataColumn(label: Text(crudText('risk.cluster.fingerprint'))),
                DataColumn(label: Text(crudText('risk.cluster.user_count'))),
                DataColumn(label: Text(crudText('risk.cluster.status'))),
                DataColumn(label: Text(crudText('risk.cluster.updated'))),
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
            size: RiskClusterManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
      ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 96),
        child: SingleChildScrollView(
          child: Text(crudText('risk.cluster.member_ids_note'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
        ),
      ),
    ]);
  }

  /// 检测结果是**内存面板**不是列表行：候选没落库、没有 hashid，行内动作放不进去。
  /// 限高自滚（最多 20 条：10 IP + 10 设备），别把下面的表格顶出屏幕。
  Widget _candidatePanel(BuildContext context, RiskClusterManageController ctrl, List<dynamic> list) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(border: Border.all(color: Colors.grey.shade400)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(crudText('risk.cluster.candidates', {'days': '${ctrl.windowDays.value}'}),
            style: const TextStyle(fontWeight: FontWeight.bold)),
        if (list.isEmpty)
          Text(crudText('app.no_data'))
        else
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 180),
            child: SingleChildScrollView(
              child: Column(children: [
                for (final c in list)
                  Row(children: [
                    Expanded(
                      child: Text('${crudEnum('risk.cluster.type', c['type'])} · '
                          '${c['fingerprint_masked']} · ${c['user_count']}'),
                    ),
                    TextButton(
                      onPressed: () => _openForm(context, ctrl, c),
                      child: Text(crudText('risk.cluster.confirm_candidate')),
                    ),
                  ]),
              ]),
            ),
          ),
      ]),
    );
  }

  DataRow _dataRow(BuildContext context, RiskClusterManageController ctrl, dynamic row) {
    final id = row['id'].toString();
    final status = (row['status'] as num?)?.toInt() ?? 1;

    return DataRow(cells: [
      DataCell(Text('${row['name']}')),
      DataCell(Text(crudEnum('risk.cluster.type', row['type']))),
      DataCell(Text('${row['fingerprint_masked']}')),
      DataCell(Text('${row['user_count']}')),
      DataCell(Text(crudEnum('risk.cluster.status', status))),
      DataCell(Text('${row['updated_at']}')),
      DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
        IconButton(
          icon: const Icon(Icons.group_outlined, size: 18),
          tooltip: crudText('risk.cluster.members'),
          onPressed: () => _members(context, ctrl, row),
        ),
        PopupMenuButton<int>(
          tooltip: crudText('risk.cluster.status'),
          // 当前值打勾，但**仍然可选**（改成同值是幂等 no-op，不是错）
          itemBuilder: (_) => [
            for (final value in _statuses)
              CheckedPopupMenuItem<int>(
                value: value,
                checked: value == status,
                child: Text(crudEnum('risk.cluster.status', value)),
              ),
          ],
          onSelected: (value) async {
            try {
              await ctrl.setStatus(id, value);
            } catch (e) {
              Get.snackbar(crudText('app.error'), apiErrorMessage(e));
            }
          },
        ),
      ])),
    ]);
  }

  /// candidate 非空 = 从候选里确认为团伙：type/fingerprint 用**原文**预填（检测结果里两个都有），
  /// user_count 也一起带过去（候选面板上刚显示过这个数，确认后变 0 就是当场丢数据）。
  /// name 给一个可改的默认名（服务端要求非空且截 100）。
  Future<void> _openForm(BuildContext context, RiskClusterManageController ctrl, dynamic candidate) async {
    final initial = candidate == null
        ? null
        : <String, dynamic>{
            'name': '${crudEnum('risk.cluster.type', candidate['type'])} ${candidate['fingerprint_masked']}',
            'type': '${candidate['type']}',
            'fingerprint': '${candidate['fingerprint']}',
            'user_count': candidate['user_count'],
          };
    final ok = await showCrudForm(
      context,
      title: candidate == null ? crudText('risk.cluster.manual') : crudText('risk.cluster.confirm_title'),
      fields: _fields,
      initial: initial,
      onSubmit: (data) async {
        // same_ip/same_device 少了 fingerprint 服务端 422；manual 允许空（本地不预判，让服务端说）
        await ctrl.confirm(data);
      },
    );
    if (ok) Get.snackbar(crudText('app.success'), crudText('app.saved'));
  }

  Future<void> _detect(BuildContext context, RiskClusterManageController ctrl) async {
    try {
      await ctrl.detect();
    } catch (e) {
      Get.snackbar(crudText('app.error'), apiErrorMessage(e));
    }
  }

  Future<void> _members(BuildContext context, RiskClusterManageController ctrl, dynamic row) async {
    final Map<String, dynamic> data;
    try {
      data = await ctrl.members(row['id'].toString());
    } catch (e) {
      Get.snackbar(crudText('app.error'), apiErrorMessage(e));
      return;
    }
    final members = data['members'] as List<dynamic>? ?? [];
    Get.dialog(AlertDialog(
      title: Text(crudText('risk.cluster.members_title', {'name': '${row['name']}'})),
      content: SizedBox(
        width: 480,
        height: 320,
        child: members.isEmpty
            ? Center(child: Text(crudText('risk.cluster.members_empty')))
            : ListView.builder(
                itemCount: members.length,
                itemBuilder: (_, index) => ListTile(
                  dense: true,
                  title: Text('${members[index]['username']}'),
                  subtitle: Text('${members[index]['id']}'),
                ),
              ),
      ),
      actions: [TextButton(onPressed: () => Get.back(), child: Text(crudText('app.close')))],
    ));
  }
}
