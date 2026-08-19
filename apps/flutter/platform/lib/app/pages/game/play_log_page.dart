// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class PlayLogController extends GetxController {
  final api = ApiService();
  final list = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); loadData(); }

  Future<void> loadData() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/api/game/play-logs', params: {'per_page': '50'});
      list.value = (resp['data']['list'] as List<dynamic>?) ?? [];
    } catch (e) {
      Get.snackbar('Error', 'Load failed: $e');
    } finally {
      isLoading.value = false;
    }
  }
}

class PlayLogPage extends GetView<PlayLogController> {
  const PlayLogPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PlayLogController>()) Get.put(PlayLogController());
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Game History', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.list.isEmpty) return const Center(child: Text('No game history'));
        return ListView.builder(
          itemCount: ctrl.list.length,
          itemBuilder: (_, i) {
            final item = ctrl.list[i];
            return ListTile(
              leading: const Icon(Icons.sports_esports),
              title: Text('Game #${item['game_id']} - ${item['action']}'),
              subtitle: Text(item['created_at']?.toString() ?? ''),
              trailing: Text(item['game_amount_change']?.toString() ?? '0'),
            );
          },
        );
      })),
    ]);
  }
}
