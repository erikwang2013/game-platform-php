// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

/// 异常用户：信任分队列 + 时间线抽屉 + 冻结（hold）
class RiskUserController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;
  final timeline = <dynamic>[].obs;
  String scoreMin = '';

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/risk/users', params: {
        if (scoreMin.isNotEmpty) 'score_min': scoreMin,
      });
      items.value = (resp['data']['items'] as List<dynamic>?) ?? [];
    } catch (e) {
      Get.snackbar('加载失败', '$e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadTimeline(String userId) async {
    timeline.value = [];
    final resp = await api.get('/admin/v1/risk/users/$userId/timeline');
    timeline.value = (resp['data']['events'] as List<dynamic>?) ?? [];
  }

  Future<void> hold(String userId) async {
    await api.post('/admin/v1/risk/users/$userId/hold');
    await load();
  }
}

class RiskUserTab extends GetView<RiskUserController> {
  const RiskUserTab({super.key});

  static const _sourceNames = {'risk': '风控', 'play': '游戏', 'anticheat': '反作弊'};
  static const _sourceColors = {'risk': Colors.red, 'play': Colors.blue, 'anticheat': Colors.orange};

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskUserController>()) Get.put(RiskUserController());
    final ctrl = controller;

    return Column(children: [
      Row(children: [
        SizedBox(
          width: 120,
          child: TextField(
            decoration: const InputDecoration(labelText: '信任分 <=', isDense: true),
            keyboardType: TextInputType.number,
            onSubmitted: (v) { ctrl.scoreMin = v; ctrl.load(); },
          ),
        ),
        const SizedBox(width: 12),
        ElevatedButton(onPressed: () => ctrl.load(), child: const Text('刷新')),
        const Spacer(),
        const Text('信任分 0-100，越低越可疑', style: TextStyle(fontSize: 12, color: Colors.grey)),
      ]),
      const SizedBox(height: 8),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) return const Center(child: Text('无异常用户'));
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final u = ctrl.items[i];
            return Card(child: ListTile(
              leading: CircleAvatar(
                backgroundColor: (u['score'] as num).toInt() < 30 ? Colors.red : Colors.orange,
                child: Text('${u['score']}'),
              ),
              title: Text('${u['username']}'),
              subtitle: Text('${u['band']} | 命中 ${u['hit_count']} | ${u['last_hit_at']}'),
              trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                IconButton(
                  icon: const Icon(Icons.history, size: 20),
                  tooltip: '时间线',
                  onPressed: () => _showTimeline(context, ctrl, u),
                ),
                IconButton(
                  icon: const Icon(Icons.lock, size: 20, color: Colors.red),
                  tooltip: '冻结余额',
                  onPressed: () => _confirmHold(context, ctrl, u),
                ),
              ],
            )));
          },
        );
      })),
    ]);
  }

  void _showTimeline(BuildContext ctx, RiskUserController ctrl, dynamic u) async {
    await ctrl.loadTimeline('${u['user_id']}');
    if (!ctx.mounted) return;
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.7,
        builder: (_, scrollCtrl) => Column(children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Text('${u['username']} 风控时间线', style: const TextStyle(fontWeight: FontWeight.bold)),
          ),
          Expanded(child: Obx(() {
            if (ctrl.timeline.isEmpty) return const Center(child: Text('暂无事件'));
            return ListView.builder(
              controller: scrollCtrl,
              itemCount: ctrl.timeline.length,
              itemBuilder: (_, i) {
                final e = ctrl.timeline[i];
                final src = '${e['source']}';
                return ListTile(
                  dense: true,
                  leading: Icon(Icons.circle, size: 10, color: _sourceColors[src] ?? Colors.grey),
                  title: Text('[${_sourceNames[src] ?? src}] ${e['type']} / ${e['action']}'),
                  subtitle: Text('${e['time']}\n${e['detail']}'),
                );
              },
            );
          })),
        ]),
      ),
    );
  }

  void _confirmHold(BuildContext ctx, RiskUserController ctrl, dynamic u) {
    showDialog(context: ctx, builder: (_) => AlertDialog(
      title: const Text('冻结账户'),
      content: Text('确认冻结 ${u['username']} 的平台可用余额？冻结后余额不可提现，可人工解冻。'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
          onPressed: () async {
            try {
              await ctrl.hold('${u['user_id']}');
              Get.snackbar('成功', '已冻结');
              Navigator.pop(ctx);
            } catch (e) {
              Get.snackbar('失败', '$e');
            }
          },
          child: const Text('确认冻结'),
        ),
      ],
    ));
  }
}
