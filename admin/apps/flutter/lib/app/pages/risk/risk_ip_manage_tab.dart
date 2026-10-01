// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// IP 信誉（ip_reputation）：只读列表 + 四个手工动作。
// 端点（admin/app/admin/v1/controller/RiskIpController.php）：
//   GET  /admin/v1/risk/ip/list?page=&size=（回 {total, items}，ip 已脱敏）
//   POST /risk/ip/{block|whitelist|appeal|recheck}   body 里是**明文 IP**（不是哈希）
// **没有解封端点**：只能靠白名单放行（appeal 与 whitelist 同效，都是 source=internal_whitelist score=100）。
// 明文 IP 是「可无中生有」的入参（不依赖列表行）⇒ 四个动作都在工具栏上手工输入，不做行内按钮。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskIpManageController extends GetxController {
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
      // 走 api.list：三种分页参数名一次发全（风控族读 `size`，但同族搜索/别处读 limit/per_page）
      final result = await api.list('/admin/v1/risk/ip/list', page: page.value, pageSize: pageSize);
      items.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar(crudText('app.error'), '${crudText('app.loading_failed')}: ${apiErrorMessage(e)}');
    } finally {
      isLoading.value = false;
    }
  }

  /// 四个动作共用一个入参形状。回包两种：三个写动作走 BaseController 默认 message（'success'，
  /// 没有信息量），recheck 把说明放在 `data.message` ⇒ 优先取后者，再退回顶层 message 并滤掉 'success'。
  Future<String> act(String action, String ip) async {
    final resp = await api.post('/admin/v1/risk/ip/$action', data: <String, dynamic>{'ip': ip});
    await load();
    final data = resp['data'];
    final inner = data is Map ? data['message'] : null;
    final msg = (inner ?? resp['message'])?.toString() ?? '';
    return msg == 'success' ? '' : msg;
  }
}

class RiskIpManageTab extends GetView<RiskIpManageController> {
  const RiskIpManageTab({super.key});

  /// 动作 → 按钮/标题文案。recheck 只删本地信誉缓存（不落库）⇒ 不在 _confirms 里，直接执行。
  static const Map<String, String> _labels = <String, String>{
    'block': 'risk.ip.block',
    'whitelist': 'risk.ip.whitelist',
    'appeal': 'risk.ip.appeal',
    'recheck': 'risk.ip.recheck',
  };
  static const Map<String, String> _confirms = <String, String>{
    'block': 'risk.ip.block_confirm',
    'whitelist': 'risk.ip.whitelist_confirm',
    'appeal': 'risk.ip.appeal_confirm',
  };
  static const Map<String, IconData> _icons = <String, IconData>{
    'block': Icons.block,
    'whitelist': Icons.verified_user_outlined,
    'appeal': Icons.gavel_outlined,
    'recheck': Icons.refresh,
  };

  /// 服务端 `filter_var(..., FILTER_VALIDATE_IP)` 不过就 400 ⇒ 本地只判非空。
  static const List<CrudField> _ipFields = <CrudField>[
    CrudField('ip', 'risk.ip.raw', required: true, hint: 'risk.ip.raw_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskIpManageController>()) {
      Get.put(RiskIpManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Wrap(spacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
        Text(crudText('risk.tab_ips'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const SizedBox(width: 16),
        for (final action in _labels.keys)
          OutlinedButton.icon(
            icon: Icon(_icons[action], size: 18),
            label: Text(crudText(_labels[action]!)),
            onPressed: () => _open(context, ctrl, action),
          ),
      ]),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.ip.hash'))),
                DataColumn(label: Text(crudText('risk.ip.score'))),
                DataColumn(label: Text(crudText('risk.ip.source'))),
                DataColumn(label: Text(crudText('risk.ip.hits'))),
                DataColumn(label: Text(crudText('risk.ip.first_seen'))),
                DataColumn(label: Text(crudText('risk.ip.last_seen'))),
              ],
              rows: [
                for (final row in ctrl.items)
                  DataRow(cells: [
                    DataCell(Text('${row['ip_masked']}')),
                    DataCell(Text('${row['reputation_score']}')),
                    DataCell(Text('${row['source']}')),
                    DataCell(Text('${row['hit_count']}')),
                    DataCell(Text('${row['first_seen_at']}')),
                    DataCell(Text('${row['last_seen_at']}')),
                  ]),
              ],
            ),
          ),
        );
      })),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: RiskIpManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
      Text(crudText('risk.ip.note'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
    ]);
  }

  Future<void> _open(BuildContext context, RiskIpManageController ctrl, String action) async {
    var message = '';
    final ok = await showCrudForm(
      context,
      title: crudText(_labels[action]!),
      fields: _ipFields,
      onSubmit: (data) async {
        final ip = '${data['ip']}';
        // 拉黑/加白/申诉都会改写评估器的判定 ⇒ 危险动作，文案里回显输入原文（发出去的正是它）
        final confirmKey = _confirms[action];
        if (confirmKey == null) {
          message = await ctrl.act(action, ip);
          return;
        }
        final done = await confirmCrudAction(
          context,
          title: '${crudText('app.confirm')} ${crudText(_labels[action]!)}',
          message: crudText(confirmKey, {'ip': ip}),
          confirmLabel: crudText(_labels[action]!),
          onConfirm: () async {
            message = await ctrl.act(action, ip);
          },
        );
        if (!done) return; // 取消 ⇒ 一个字节都不发
        message = message.isEmpty ? crudText('app.saved') : message;
      },
    );
    if (ok && message.isNotEmpty) Get.snackbar(crudText('app.success'), message);
  }
}
