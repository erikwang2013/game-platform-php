// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class GameListController extends GetxController {
  final api = ApiService();
  final games = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadGames();
  }

  Future<void> loadGames() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/game/list');
      games.value = resp['data'] is List ? resp['data'] as List<dynamic> : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载游戏列表失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> create(Map<String, dynamic> data) async {
    try {
      await api.post('/admin/v1/game/create', data: data);
      await loadGames();
      Get.snackbar('成功', 'gameCreateSuccess');
    } catch (e) {
      Get.snackbar('错误', '创建失败: $e');
    }
  }

  Future<void> updateGame(String hashid, Map<String, dynamic> data) async {
    try {
      await api.put('/admin/v1/game/$hashid', data: data);
      await loadGames();
      Get.snackbar('成功', 'gameUpdateSuccess');
    } catch (e) {
      Get.snackbar('错误', '更新失败: $e');
    }
  }

  Future<void> remove(String hashid) async {
    try {
      await api.delete('/admin/v1/game/$hashid');
      await loadGames();
      Get.snackbar('成功', 'gameDeleteSuccess');
    } catch (e) {
      Get.snackbar('错误', '删除失败: $e');
    }
  }
}

class GameListPage extends GetView<GameListController> {
  const GameListPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<GameListController>()) {
      Get.put(GameListController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text("${AppTranslations.t('game.title')}", style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            FloatingActionButton.small(
              heroTag: 'addGame',
              onPressed: () => _showGameDialog(context, ctrl),
              child: const Icon(Icons.add),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.games.isEmpty) return Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Text('${AppTranslations.t('game.id')}')),
                  DataColumn(label: Text("${AppTranslations.t('game.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.slug')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.type')}")),
                  DataColumn(label: Text("${AppTranslations.t('game.currencies')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.games.map((g) {
                  final id = g['id']?.toString() ?? '';
                  final name = g['name']?.toString() ?? '';
                  final slug = g['slug']?.toString() ?? '';
                  final type = g['type']?.toString() ?? '';
                  final typeLabel = type == 'self' ? '${AppTranslations.t('game.self')}' : '${AppTranslations.t('game.third_party')}';
                  final currencyCount = (g['currency_count'] ?? g['currencies'] is List ? (g['currencies'] as List).length : 0).toString();
                  final status = g['status'] is int ? g['status'] : (g['status'] == 'active' || g['status'] == true ? 1 : 0);
                  final statusLabel = status == 1 ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}";

                  return DataRow(cells: [
                    DataCell(Text(id)),
                    DataCell(Text(name)),
                    DataCell(Text(slug)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Text(currencyCount)),
                    DataCell(Chip(
                      label: Text(statusLabel),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.edit, size: 18),
                          onPressed: () => _showGameDialog(context, ctrl, item: g),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                          onPressed: () => _confirmDelete(context, ctrl, g),
                        ),
                      ],
                    )),
                  ]);
                }).toList(),
              ),
            );
          }),
        ),
      ],
    );
  }

  void _showGameDialog(BuildContext context, GameListController ctrl, {dynamic item}) {
    final nameCtrl = TextEditingController(text: item?['name'] ?? '');
    final slugCtrl = TextEditingController(text: item?['slug'] ?? '');
    final descCtrl = TextEditingController(text: item?['description'] ?? '');
    final coverCtrl = TextEditingController(text: item?['cover_image'] ?? '');
    final endpointCtrl = TextEditingController(text: item?['api_endpoint'] ?? '');
    final sortCtrl = TextEditingController(text: item?['sort']?.toString() ?? '0');
    String type = item?['type']?.toString() ?? 'self';
    bool isEnabled = item != null ? (item['status'] == 1 || item['status'] == true) : true;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Text(item != null ? '${AppTranslations.t('game.edit')}' : '${AppTranslations.t('game.create')}'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: nameCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.name')}')),
                const SizedBox(height: 12),
                TextField(controller: slugCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.slug')}')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: type,
                  decoration: InputDecoration(labelText: '${AppTranslations.t('game.type')}'),
                  items: [
                    DropdownMenuItem(value: 'self', child: Text('${AppTranslations.t('game.self')}')),
                    DropdownMenuItem(value: 'third_party', child: Text('${AppTranslations.t('game.third_party')}')),
                  ],
                  onChanged: (v) {
                    if (v != null) {
                      setDialogState(() => type = v);
                    }
                  },
                ),
                const SizedBox(height: 12),
                TextField(controller: descCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.description')}'), maxLines: 2),
                const SizedBox(height: 12),
                TextField(controller: coverCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.cover_image')}')),
                const SizedBox(height: 12),
                TextField(controller: endpointCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.api_endpoint')}')),
                const SizedBox(height: 12),
                TextField(controller: sortCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('game.sort')}'), keyboardType: TextInputType.number),
                const SizedBox(height: 12),
                SwitchListTile(
                  title: Text("${AppTranslations.t('app.enabled')}"),
                  value: isEnabled,
                  onChanged: (v) => setDialogState(() => isEnabled = v),
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: Text("${AppTranslations.t('app.cancel')}")),
            ElevatedButton(
              onPressed: () {
                final data = <String, dynamic>{
                  'name': nameCtrl.text,
                  'slug': slugCtrl.text,
                  'type': type,
                  'description': descCtrl.text,
                  'cover_image': coverCtrl.text,
                  'api_endpoint': endpointCtrl.text,
                  'sort': int.tryParse(sortCtrl.text) ?? 0,
                  'status': isEnabled ? 1 : 0,
                };
                if (item != null) {
                  ctrl.updateGame(item['id'].toString(), data);
                } else {
                  ctrl.create(data);
                }
                Navigator.pop(ctx);
              },
              child: Text("${AppTranslations.t('app.save')}"),
            ),
          ],
        ),
      ),
    );
  }

  void _confirmDelete(BuildContext context, GameListController ctrl, dynamic game) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text("${AppTranslations.t('app.confirm')}"
          + ' '
          + "${AppTranslations.t('app.delete')}"),
        content: Text('确定要删除游戏「${game['name']}」吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(
            onPressed: () {
              ctrl.remove(game['id'].toString());
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            child: Text("${AppTranslations.t('app.delete')}"),
          ),
        ],
      ),
    );
  }
}
