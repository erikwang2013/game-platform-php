// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class CdnController extends GetxController {
  final api = ApiService();
  final providers = <dynamic>[].obs;
  final isLoading = false.obs;
  final testingId = ''.obs;

  static const providerOptions = ['cloudflare', 'cloudfront', 'aliyun', 'tencent', 'huawei'];

  @override
  void onInit() {
    super.onInit();
    loadProviders();
  }

  Future<void> loadProviders() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/cdn/provider/list');
      providers.value = resp['data'] is List
          ? resp['data'] as List<dynamic>
          : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载 CDN 厂商失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggleProvider(String hashid, bool enabled) async {
    try {
      await api.post('/admin/v1/cdn/provider/toggle', data: {
        'id': hashid,
        'status': enabled ? 1 : 0,
      });
      await loadProviders();
      Get.snackbar('成功', enabled ? 'CDN 厂商已启用' : 'CDN 厂商已禁用');
    } catch (e) {
      await loadProviders();
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  Future<void> testProvider(String hashid) async {
    testingId.value = hashid;
    try {
      await api.post('/admin/v1/cdn/provider/test', data: {'id': hashid});
      Get.snackbar('成功', "${AppTranslations.t('cdn.test_success')}");
    } catch (e) {
      Get.snackbar('错误', "${AppTranslations.t('cdn.test_fail')}: $e");
    } finally {
      testingId.value = '';
    }
  }

  Future<bool> saveProvider({String? hashid, required Map<String, dynamic> data}) async {
    try {
      if (hashid == null) {
        await api.post('/admin/v1/cdn/provider/create', data: data);
      } else {
        await api.put('/admin/v1/cdn/provider/$hashid', data: data);
      }
      await loadProviders();
      return true;
    } catch (e) {
      Get.snackbar('错误', '保存失败: $e');
      return false;
    }
  }

  Future<bool> deleteProvider(String hashid) async {
    try {
      await api.delete('/admin/v1/cdn/provider/$hashid');
      await loadProviders();
      return true;
    } catch (e) {
      Get.snackbar('错误', '删除失败: $e');
      return false;
    }
  }
}

class CdnPage extends GetView<CdnController> {
  const CdnPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<CdnController>()) {
      Get.put(CdnController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text("${AppTranslations.t('cdn.title')}",
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            FilledButton.icon(
              icon: const Icon(Icons.add, size: 18),
              label: Text("${AppTranslations.t('app.create')}"),
              onPressed: () => _openForm(context),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.providers.isEmpty) return Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Text("${AppTranslations.t('payment.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('cdn.provider')}")),
                  DataColumn(label: Text("${AppTranslations.t('cdn.domain')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.status')}")),
                  DataColumn(label: Text("${AppTranslations.t('withdraw.actions')}")),
                ],
                rows: ctrl.providers.map((m) {
                  final name = m['name']?.toString() ?? '';
                  final provider = m['provider']?.toString() ?? '';
                  final status = m['status'] is int ? m['status'] : (m['status'] == 'active' || m['status'] == true ? 1 : 0);
                  final isEnabled = status == 1;
                  final hashid = m['id']?.toString() ?? '';

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    DataCell(Text(provider)),
                    DataCell(Text('-')),
                    DataCell(Chip(
                      label: Text(isEnabled ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"),
                      color: WidgetStatePropertyAll(isEnabled ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Switch(
                          value: isEnabled,
                          onChanged: (v) => ctrl.toggleProvider(hashid, v),
                        ),
                        IconButton(
                          icon: ctrl.testingId.value == hashid
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.wifi_tethering, size: 18),
                          tooltip: "${AppTranslations.t('cdn.test')}",
                          onPressed: ctrl.testingId.value.isEmpty ? () => ctrl.testProvider(hashid) : null,
                        ),
                        IconButton(
                          icon: const Icon(Icons.edit, size: 18),
                          tooltip: "${AppTranslations.t('app.edit')}",
                          onPressed: () => _openForm(context, provider: m),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                          tooltip: "${AppTranslations.t('app.delete')}",
                          onPressed: () => _confirmDelete(context, hashid, name),
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

  void _openForm(BuildContext context, {Map<String, dynamic>? provider}) {
    showDialog(
      context: context,
      builder: (_) => _CdnFormDialog(provider: provider),
    );
  }

  void _confirmDelete(BuildContext context, String hashid, String name) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text("${AppTranslations.t('app.delete')}"),
        content: Text("${AppTranslations.t('cdn.confirm_delete')}\n$name"),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text("${AppTranslations.t('app.cancel')}"),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final ok = await controller.deleteProvider(hashid);
              if (ok) Get.snackbar('成功', "${AppTranslations.t('app.deleted')}");
            },
            child: Text("${AppTranslations.t('app.confirm')}", style: const TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }
}

class _CdnFormDialog extends StatefulWidget {
  final Map<String, dynamic>? provider;
  const _CdnFormDialog({this.provider});

  @override
  State<_CdnFormDialog> createState() => _CdnFormDialogState();
}

class _CdnFormDialogState extends State<_CdnFormDialog> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _configCtrl;

  late String _provider;
  late bool _status;

  bool get _editing => widget.provider != null;

  @override
  void initState() {
    super.initState();
    final m = widget.provider;
    _nameCtrl = TextEditingController(text: m?['name']?.toString() ?? '');
    _configCtrl = TextEditingController(text: m?['config']?.toString() ?? '');

    _provider = m?['provider']?.toString() ?? 'cloudflare';
    _status = (m?['status'] ?? 1) == 1;
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _configCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameCtrl.text.trim();
    if (name.isEmpty || _provider.isEmpty) {
      Get.snackbar('错误', '名称与厂商不能为空');
      return;
    }

    final data = <String, dynamic>{
      'name': name,
      'provider': _provider,
      'status': _status ? 1 : 0,
    };
    final config = _configCtrl.text.trim();
    if (config.isNotEmpty) {
      data['config'] = config;
    }

    final ok = await Get.find<CdnController>().saveProvider(
      hashid: _editing ? widget.provider!['id']?.toString() : null,
      data: data,
    );
    if (ok && mounted) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final t = AppTranslations.t;
    return AlertDialog(
      title: Text(_editing ? "${t('cdn.edit_title')}" : "${t('cdn.create_title')}"),
      content: SingleChildScrollView(
        child: SizedBox(
          width: 440,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: _nameCtrl,
                decoration: InputDecoration(labelText: "${t('payment.name')}", isDense: true),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _provider,
                decoration: InputDecoration(labelText: "${t('cdn.provider')}", isDense: true),
                items: CdnController.providerOptions
                    .map((p) => DropdownMenuItem(value: p, child: Text(p)))
                    .toList(),
                onChanged: (v) => setState(() => _provider = v ?? 'cloudflare'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _configCtrl,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: "${t('payment.config')}",
                  hintText: '如 {"api_key":"xxx","secret_key":"xxx"}',
                  border: const OutlineInputBorder(),
                  isDense: true,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Expanded(child: SizedBox()),
                  Switch(
                    value: _status,
                    onChanged: (v) => setState(() => _status = v),
                  ),
                  const SizedBox(width: 8),
                  Text(_status ? "${t('app.enabled')}" : "${t('app.disabled')}"),
                ],
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text("${t('app.cancel')}"),
        ),
        FilledButton(
          onPressed: _submit,
          child: Text("${t('app.save')}"),
        ),
      ],
    );
  }
}
