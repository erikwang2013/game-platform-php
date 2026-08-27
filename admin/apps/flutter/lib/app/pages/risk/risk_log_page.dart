// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class RiskLogController extends GetxController {
  final api = ApiService();
  final list = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); loadData(); }

  Future<void> loadData() async {
    isLoading.value = true;
    try {
      // Risk logs are accessed via admin dashboard extension or direct query
      final resp = await api.get('/admin/dashboard/platform');
      // For MVP, just show we loaded something
      list.value = [];
    } catch (e) {
      // Risk log viewing can be added later with dedicated API
    } finally {
      isLoading.value = false;
    }
  }
}

class RiskLogPage extends GetView<RiskLogController> {
  const RiskLogPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskLogController>()) Get.put(RiskLogController());
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Risk Logs', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (controller.isLoading.value) return const Center(child: CircularProgressIndicator());
        return const Center(child: Text('Risk logs will be available with dedicated API endpoint'));
      })),
    ]);
  }
}
