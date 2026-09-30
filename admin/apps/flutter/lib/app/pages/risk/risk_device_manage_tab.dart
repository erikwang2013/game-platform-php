// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 设备指纹（device_fingerprint）：只读列表 + **行内**拉黑 / 解封。
// 端点（admin/app/admin/v1/controller/RiskDeviceController.php）：
//   GET  /admin/v1/risk/device/list?page=&size=  —— 每行同时回 fp_hash（完整，动作入参）与 fp_masked（展示）
//   POST /risk/device/block   {fp_hash}          —— 管理端 Redis 标记，TTL 30 天
//   POST /risk/device/unblock {fp_hash}
// 行里**没有** id/hashid：设备行的标识就是完整指纹本身，两个动作都发 {fp_hash: row.fp_hash}。
// 拉黑在 RiskService::check() 里于**规则循环之前短路成 block**（不看那条 device 规则开没开，
// install.sql 种子里它本来就是停用的）⇒ 拉黑之后充提真的会被拒，不是「只标记不阻断」。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class RiskDeviceManageController extends GetxController {
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
      final resp = await api.get('/admin/v1/risk/device/list', params: <String, dynamic>{
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

  /// block 回 data.fp_masked（请求里已知的完整哈希的截断版）、unblock 回默认 success
  /// ⇒ 两个回包都没有可读文案，页面统一用 app.saved。
  Future<void> block(String fpHash) async {
    await api.post('/admin/v1/risk/device/block', data: <String, dynamic>{'fp_hash': fpHash});
    await load();
  }

  Future<void> unblock(String fpHash) async {
    await api.post('/admin/v1/risk/device/unblock', data: <String, dynamic>{'fp_hash': fpHash});
    await load();
  }
}

class RiskDeviceManageTab extends GetView<RiskDeviceManageController> {
  const RiskDeviceManageTab({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskDeviceManageController>()) {
      Get.put(RiskDeviceManageController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(crudText('risk.tab_devices'), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return Center(child: Text(crudText('app.no_data')));
        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text(crudText('risk.device.fp'))),
                DataColumn(label: Text(crudText('risk.device.ip_c'))),
                DataColumn(label: Text(crudText('risk.device.accounts'))),
                DataColumn(label: Text(crudText('risk.device.first_seen'))),
                DataColumn(label: Text(crudText('risk.device.last_seen'))),
                DataColumn(label: Text(crudText('risk.device.blocked'))),
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
            size: RiskDeviceManageController.pageSize,
            onPage: (next) => ctrl.load(toPage: next),
          )),
      Text(crudText('risk.device.note'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
    ]);
  }

  DataRow _dataRow(BuildContext context, RiskDeviceManageController ctrl, dynamic row) {
    final blocked = row['blocked'] == true;
    return DataRow(cells: [
      DataCell(Text('${row['fp_masked']}')),
      DataCell(Text('${row['ip_c_segment']}')),
      DataCell(Text('${row['account_count']}')),
      DataCell(Text('${row['first_seen_at']}')),
      DataCell(Text('${row['last_seen_at']}')),
      DataCell(Text(blocked ? crudText('app.yes') : crudText('app.no'))),
      DataCell(IconButton(
        // 动作由**这一行自己的** blocked 决定：已拉黑的行只能解封、未拉黑的行只能拉黑。
        // 不用 Switch：翻转做不出二次确认，而且拉黑/解封不是对称的启停（一个是真会拒充提的限制）。
        icon: Icon(blocked ? Icons.lock_open : Icons.block, size: 18),
        tooltip: crudText(blocked ? 'risk.device.unblock' : 'risk.device.block'),
        onPressed: () => _act(
          context,
          ctrl,
          blocking: !blocked,
          fpHash: '${row['fp_hash']}',
          masked: '${row['fp_masked']}',
        ),
      )),
    ]);
  }

  /// 两个方向都过二次确认，文案都带 **fp_masked**：行是按掩码认的，确认框里必须出现同一个掩码，
  /// 否则「确认拉黑 a1b2c3d4****」对列表里任何一行都成立。
  /// 完整 fp_hash 只进请求体、不进文案（64 位十六进制没人核对得了）。
  Future<void> _act(
    BuildContext context,
    RiskDeviceManageController ctrl, {
    required bool blocking,
    required String fpHash,
    required String masked,
  }) async {
    // 取消与失败都不会走到这里：confirmCrudAction 两条路径都返回 false（失败还自带错误提示）
    final done = await confirmCrudAction(
      context,
      title: '${crudText('app.confirm')} ${crudText(blocking ? 'risk.device.block' : 'risk.device.unblock')}',
      message: crudText(blocking ? 'risk.device.block_confirm' : 'risk.device.unblock_confirm', {'fp': masked}),
      confirmLabel: crudText(blocking ? 'risk.device.block' : 'risk.device.unblock'),
      onConfirm: () => blocking ? ctrl.block(fpHash) : ctrl.unblock(fpHash),
    );
    if (done) Get.snackbar(crudText('app.success'), crudText('app.saved'));
  }
}
