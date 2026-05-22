// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class AnnouncementController extends GetxController {
  final api = ApiService();
  final announcements = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadAnnouncements();
  }

  Future<void> loadAnnouncements() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/announcement/list');
      announcements.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载公告失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> create(Map<String, dynamic> data) async {
    try {
      await api.post('/admin/announcement/create', data: data);
      await loadAnnouncements();
      Get.snackbar('成功', '公告创建成功');
    } catch (e) {
      Get.snackbar('错误', '创建失败: $e');
    }
  }
}

class AnnouncementPage extends GetView<AnnouncementController> {
  const AnnouncementPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<AnnouncementController>()) {
      Get.put(AnnouncementController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Text('公告管理', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            FloatingActionButton.small(
              heroTag: 'addAnnouncement',
              onPressed: () => _showCreateDialog(context, ctrl),
              child: const Icon(Icons.add),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.announcements.isEmpty) return const Center(child: Text('暂无数据'));

            return SingleChildScrollView(
              child: DataTable(
                columns: const [
                  DataColumn(label: Text('标题')),
                  DataColumn(label: Text('类型')),
                  DataColumn(label: Text('状态')),
                  DataColumn(label: Text('发布时间')),
                ],
                rows: ctrl.announcements.map((a) {
                  final title = a['title']?.toString() ?? '';
                  final type = a['type']?.toString() ?? '';
                  final typeLabel = type == 'system' ? '系统公告' : (type == 'event' ? '活动公告' : type);
                  final status = a['status'] is int ? a['status'] : (a['status'] == 'active' || a['status'] == true ? 1 : 0);
                  final createdAt = a['created_at']?.toString() ?? a['published_at']?.toString() ?? '';

                  return DataRow(cells: [
                    DataCell(Text(title)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Chip(
                      label: Text(status == 1 ? '已发布' : '草稿'),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.grey.shade200),
                    )),
                    DataCell(Text(createdAt)),
                  ]);
                }).toList(),
              ),
            );
          }),
        ),
      ],
    );
  }

  void _showCreateDialog(BuildContext context, AnnouncementController ctrl) {
    final titleCtrl = TextEditingController();
    final contentCtrl = TextEditingController();
    String type = 'system';
    bool isPublished = true;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('创建公告'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: '标题')),
                const SizedBox(height: 12),
                TextField(
                  controller: contentCtrl,
                  decoration: const InputDecoration(labelText: '内容'),
                  maxLines: 5,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: type,
                  decoration: const InputDecoration(labelText: '类型'),
                  items: const [
                    DropdownMenuItem(value: 'system', child: Text('系统公告')),
                    DropdownMenuItem(value: 'event', child: Text('活动公告')),
                    DropdownMenuItem(value: 'maintenance', child: Text('维护公告')),
                  ],
                  onChanged: (v) {
                    if (v != null) {
                      setDialogState(() => type = v);
                    }
                  },
                ),
                const SizedBox(height: 12),
                SwitchListTile(
                  title: const Text('发布'),
                  value: isPublished,
                  onChanged: (v) => setDialogState(() => isPublished = v),
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
            ElevatedButton(
              onPressed: () {
                ctrl.create({
                  'title': titleCtrl.text,
                  'content': contentCtrl.text,
                  'type': type,
                  'status': isPublished ? 1 : 0,
                });
                Navigator.pop(ctx);
              },
              child: const Text('创建'),
            ),
          ],
        ),
      ),
    );
  }
}
