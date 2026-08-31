// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

/// 图谱：已确认团伙列表 + 检测候选 + 人工确认 + 成员钻取 + 状态变更
class RiskClusterController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final total = 0.obs;
  final isLoading = false.obs;
  String typeFilter = '';
  String statusFilter = '';

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/risk/clusters', params: {
        if (typeFilter.isNotEmpty) 'type': typeFilter,
        if (statusFilter.isNotEmpty) 'status': statusFilter,
      });
      items.value = (resp['data']['items'] as List<dynamic>?) ?? [];
      total.value = (resp['data']['total'] as num?)?.toInt() ?? 0;
    } catch (e) {
      Get.snackbar('加载失败', '$e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<List<dynamic>> detect() async {
    final resp = await api.post('/admin/risk/clusters/detect');
    return (resp['data']['candidates'] as List<dynamic>?) ?? [];
  }

  Future<void> confirm(Map<String, dynamic> data) async {
    await api.post('/admin/risk/clusters/confirm', data: data);
    await load();
  }

  Future<List<dynamic>> members(String id) async {
    final resp = await api.get('/admin/risk/clusters/$id/members');
    return (resp['data']['members'] as List<dynamic>?) ?? [];
  }

  Future<void> setStatus(String id, int status) async {
    await api.put('/admin/risk/clusters/$id/status', data: {'status': status});
    await load();
  }
}

class RiskClusterTab extends GetView<RiskClusterController> {
  const RiskClusterTab({super.key});

  static const _statusNames = {0: '误判', 1: '观察中', 2: '已处置'};

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskClusterController>()) Get.put(RiskClusterController());
    final ctrl = controller;

    return Column(children: [
      Row(children: [
        DropdownButton<String>(
          value: ctrl.typeFilter.isEmpty ? null : ctrl.typeFilter,
          hint: const Text('全部类型'),
          items: const [
            DropdownMenuItem(value: 'same_ip', child: Text('同IP')),
            DropdownMenuItem(value: 'same_device', child: Text('同设备')),
            DropdownMenuItem(value: 'same_pay_account', child: Text('同支付账户')),
            DropdownMenuItem(value: 'manual', child: Text('人工')),
          ],
          onChanged: (v) { ctrl.typeFilter = v ?? ''; ctrl.load(); },
        ),
        const SizedBox(width: 12),
        DropdownButton<String>(
          value: ctrl.statusFilter.isEmpty ? null : ctrl.statusFilter,
          hint: const Text('全部状态'),
          items: const [
            DropdownMenuItem(value: '0', child: Text('误判')),
            DropdownMenuItem(value: '1', child: Text('观察中')),
            DropdownMenuItem(value: '2', child: Text('已处置')),
          ],
          onChanged: (v) { ctrl.statusFilter = v ?? ''; ctrl.load(); },
        ),
        const Spacer(),
        ElevatedButton.icon(
          onPressed: () => _showDetectDialog(context, ctrl),
          icon: const Icon(Icons.radar, size: 18),
          label: const Text('聚类检测'),
        ),
      ]),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return const Center(child: Text('暂无团伙，点击"聚类检测"扫描候选'));
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final item = ctrl.items[i];
            return Card(child: ExpansionTile(
              leading: Icon(item['type'] == 'same_ip' ? Icons.lan : Icons.devices),
              title: Text('${item['name']}'),
              subtitle: Text('${item['type']} | ${item['fingerprint_masked']} | 成员 ${item['user_count']} | '
                  '${_statusNames[item['status']] ?? item['status']} | ${item['created_at']}'),
              trailing: PopupMenuButton<int>(
                onSelected: (s) => ctrl.setStatus('${item['id']}', s),
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 1, child: Text('观察中')),
                  PopupMenuItem(value: 2, child: Text('已处置')),
                  PopupMenuItem(value: 0, child: Text('误判')),
                ],
              ),
              onExpansionChanged: (_) async {
                final members = await ctrl.members('${item['id']}');
                item['_members'] = members;
              },
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text((item['_members'] as List<dynamic>? ?? [])
                        .map((m) => '${m['username']}(${m['id']})').join('，'), style: const TextStyle(fontSize: 12)),
                  ),
                ),
              ],
            ));
          },
        );
      })),
    ]);
  }

  void _showDetectDialog(BuildContext ctx, RiskClusterController ctrl) {
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: const Text('聚类检测（近7天候选）'),
      content: SizedBox(
        width: 420,
        child: FutureBuilder<List<dynamic>>(
          future: ctrl.detect(),
          builder: (_, snap) {
            if (snap.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
            final cands = snap.data ?? [];
            if (cands.isEmpty) return const Text('无候选：同IP<5账户 或 同设备<3账户');
            return ListView.builder(
              shrinkWrap: true,
              itemCount: cands.length,
              itemBuilder: (_, i) {
                final c = cands[i];
                return ListTile(
                  dense: true,
                  title: Text('${c['type']} ${c['fingerprint_masked']}'),
                  subtitle: Text('${c['user_count']} 个账户'),
                  onTap: () => _showConfirmDialog(ctx, ctrl, c),
                );
              },
            );
          },
        ),
      ),
      actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('关闭'))],
    ));
  }

  void _showConfirmDialog(BuildContext ctx, RiskClusterController ctrl, Map<String, dynamic> cand) {
    final nameCtrl = TextEditingController();
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: Text('确认团伙（${cand['type']}）'),
      content: TextField(
        controller: nameCtrl,
        decoration: const InputDecoration(labelText: '团伙名称', hintText: '如：202608 同IP刷量团'),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
        ElevatedButton(
          onPressed: () async {
            if (nameCtrl.text.trim().isEmpty) return;
            try {
              await ctrl.confirm({
                'type': cand['type'],
                'fingerprint': cand['fingerprint'],
                'name': nameCtrl.text.trim(),
                'user_count': cand['user_count'],
              });
              Get.snackbar('成功', '团伙已确认');
              Navigator.pop(ctx);
              Navigator.pop(ctx);
            } catch (e) {
              Get.snackbar('失败', '$e');
            }
          },
          child: const Text('确认'),
        ),
      ],
    ));
  }
}
