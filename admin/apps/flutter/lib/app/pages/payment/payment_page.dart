// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class PaymentController extends GetxController {
  final api = ApiService();
  final methods = <dynamic>[].obs;
  final isLoading = false.obs;

  static const providerOptions = ['stripe', 'nowpayments', 'coinbase'];
  static const countryOptions = ['CN', 'US', 'JP', 'KR', 'BR', 'IN', 'DE', 'GB'];

  @override
  void onInit() {
    super.onInit();
    loadMethods();
  }

  Future<void> loadMethods() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/payment/method/list');
      methods.value = resp['data'] is List
          ? resp['data'] as List<dynamic>
          : (resp['data']['list'] as List<dynamic>? ?? []);
    } catch (e) {
      Get.snackbar('错误', '加载支付方式失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> toggleMethod(String hashid, bool enabled) async {
    try {
      await api.post('/admin/payment/method/toggle', data: {
        'id': hashid,
        'status': enabled ? 1 : 0,
      });
      await loadMethods();
      Get.snackbar('成功', enabled ? '支付方式已启用' : '支付方式已禁用');
    } catch (e) {
      await loadMethods();
      Get.snackbar('错误', '操作失败: $e');
    }
  }

  Future<bool> saveMethod({String? hashid, required Map<String, dynamic> data}) async {
    try {
      if (hashid == null) {
        await api.post('/admin/payment/method/create', data: data);
      } else {
        await api.put('/admin/payment/method/$hashid', data: data);
      }
      await loadMethods();
      return true;
    } catch (e) {
      Get.snackbar('错误', '保存失败: $e');
      return false;
    }
  }

  Future<bool> deleteMethod(String hashid) async {
    try {
      await api.delete('/admin/payment/method/$hashid');
      await loadMethods();
      return true;
    } catch (e) {
      Get.snackbar('错误', '删除失败: $e');
      return false;
    }
  }
}

class PaymentPage extends GetView<PaymentController> {
  const PaymentPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PaymentController>()) {
      Get.put(PaymentController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text("${AppTranslations.t('payment.title')}",
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
            if (ctrl.methods.isEmpty) return Center(child: Text("${AppTranslations.t('app.no_data')}"));

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Text("${AppTranslations.t('payment.name')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.type')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.provider')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.min_amount')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.max_amount')}")),
                  DataColumn(label: Text("${AppTranslations.t('payment.status')}")),
                  DataColumn(label: Text("${AppTranslations.t('withdraw.actions')}")),
                ],
                rows: ctrl.methods.map((m) {
                  final name = m['name']?.toString() ?? '';
                  final type = m['type']?.toString() ?? '';
                  final typeLabel = type == 'fiat' ? "${AppTranslations.t('payment.fiat')}" : "${AppTranslations.t('payment.crypto')}";
                  final provider = m['provider']?.toString() ?? '';
                  final minAmount = m['min_amount']?.toString() ?? '0';
                  final maxAmount = m['max_amount']?.toString() ?? '0';
                  final status = m['status'] is int ? m['status'] : (m['status'] == 'active' || m['status'] == true ? 1 : 0);
                  final isEnabled = status == 1;
                  final hashid = m['id']?.toString() ?? '';

                  return DataRow(cells: [
                    DataCell(Text(name)),
                    DataCell(Chip(label: Text(typeLabel))),
                    DataCell(Text(provider)),
                    DataCell(Text(minAmount)),
                    DataCell(Text(maxAmount)),
                    DataCell(Chip(
                      label: Text(isEnabled ? "${AppTranslations.t('app.enabled')}" : "${AppTranslations.t('app.disabled')}"),
                      color: WidgetStatePropertyAll(isEnabled ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Switch(
                          value: isEnabled,
                          onChanged: (v) => ctrl.toggleMethod(hashid, v),
                        ),
                        IconButton(
                          icon: const Icon(Icons.edit, size: 18),
                          tooltip: "${AppTranslations.t('app.edit')}",
                          onPressed: () => _openForm(context, method: m),
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

  void _openForm(BuildContext context, {Map<String, dynamic>? method}) {
    showDialog(
      context: context,
      builder: (_) => _MethodFormDialog(method: method),
    );
  }

  void _confirmDelete(BuildContext context, String hashid, String name) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text("${AppTranslations.t('app.delete')}"),
        content: Text("${AppTranslations.t('payment.confirm_delete')}\n$name"),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text("${AppTranslations.t('app.cancel')}"),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final ok = await controller.deleteMethod(hashid);
              if (ok) Get.snackbar('成功', "${AppTranslations.t('app.deleted')}");
            },
            child: Text("${AppTranslations.t('app.confirm')}", style: const TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }
}

class _MethodFormDialog extends StatefulWidget {
  final Map<String, dynamic>? method;
  const _MethodFormDialog({this.method});

  @override
  State<_MethodFormDialog> createState() => _MethodFormDialogState();
}

class _MethodFormDialogState extends State<_MethodFormDialog> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _sortCtrl;
  late final TextEditingController _currencyCtrl;
  late final TextEditingController _minCtrl;
  late final TextEditingController _maxCtrl;
  late final TextEditingController _configCtrl;

  late String _type;
  late String _provider;
  late bool _status;
  late bool _global;
  late Set<String> _countries;

  bool get _editing => widget.method != null;

  @override
  void initState() {
    super.initState();
    final m = widget.method;
    _nameCtrl = TextEditingController(text: m?['name']?.toString() ?? '');
    _sortCtrl = TextEditingController(text: (m?['sort'] ?? 0).toString());
    _currencyCtrl = TextEditingController(text: m?['currency']?.toString() ?? '');
    _minCtrl = TextEditingController(text: m?['min_amount']?.toString() ?? '0');
    _maxCtrl = TextEditingController(text: m?['max_amount']?.toString() ?? '0');
    _configCtrl = TextEditingController(text: m?['config']?.toString() ?? '');

    _type = m?['type']?.toString() ?? 'fiat';
    _provider = m?['provider']?.toString() ?? 'stripe';
    _status = (m?['status'] ?? 1) == 1;

    final codes = (m?['countries'] is List) ? (m?['countries'] as List).map((e) => e.toString()).toList() : <String>[];
    _global = codes.isEmpty || codes.contains('*');
    _countries = _global ? <String>{} : codes.toSet();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _sortCtrl.dispose();
    _currencyCtrl.dispose();
    _minCtrl.dispose();
    _maxCtrl.dispose();
    _configCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameCtrl.text.trim();
    if (name.isEmpty || _provider.isEmpty) {
      Get.snackbar('错误', '名称与提供商不能为空');
      return;
    }

    final data = <String, dynamic>{
      'name': name,
      'type': _type,
      'provider': _provider,
      'status': _status ? 1 : 0,
      'sort': int.tryParse(_sortCtrl.text.trim()) ?? 0,
      'countries': _global ? <String>[] : _countries.toList(),
      'currency': _currencyCtrl.text.trim(),
      'min_amount': _minCtrl.text.trim().isEmpty ? '0' : _minCtrl.text.trim(),
      'max_amount': _maxCtrl.text.trim().isEmpty ? '0' : _maxCtrl.text.trim(),
    };
    final config = _configCtrl.text.trim();
    if (config.isNotEmpty) {
      data['config'] = config;
    }

    final ok = await Get.find<PaymentController>().saveMethod(
      hashid: _editing ? widget.method!['id']?.toString() : null,
      data: data,
    );
    if (ok && mounted) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final t = AppTranslations.t;
    return AlertDialog(
      title: Text(_editing ? "${t('payment.edit_title')}" : "${t('payment.create_title')}"),
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
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _type,
                      decoration: InputDecoration(labelText: "${t('payment.type')}", isDense: true),
                      items: const [
                        DropdownMenuItem(value: 'fiat', child: Text('Fiat')),
                        DropdownMenuItem(value: 'crypto', child: Text('Crypto')),
                      ],
                      onChanged: (v) => setState(() => _type = v ?? 'fiat'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _provider,
                      decoration: InputDecoration(labelText: "${t('payment.provider')}", isDense: true),
                      items: PaymentController.providerOptions
                          .map((p) => DropdownMenuItem(value: p, child: Text(p)))
                          .toList(),
                      onChanged: (v) => setState(() => _provider = v ?? 'stripe'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _sortCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(labelText: "${t('payment.sort')}", isDense: true),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _currencyCtrl,
                      decoration: InputDecoration(labelText: "${t('payment.currency')}", isDense: true,
                          hintText: 'USD / USDT / 留空任意'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _minCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(labelText: "${t('payment.min_amount')}", isDense: true),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _maxCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(labelText: "${t('payment.max_amount')}",
                          helperText: '0 = 不限', isDense: true),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: Text("${t('payment.global')}", style: const TextStyle(fontSize: 14)),
                value: _global,
                onChanged: (v) => setState(() {
                  _global = v;
                  if (v) _countries = <String>{};
                }),
              ),
              if (!_global) ...[
                Wrap(
                  spacing: 6,
                  children: PaymentController.countryOptions.map((c) {
                    final selected = _countries.contains(c);
                    return FilterChip(
                      label: Text(c),
                      selected: selected,
                      onSelected: (v) => setState(() {
                        if (v) {
                          _countries.add(c);
                        } else {
                          _countries.remove(c);
                        }
                      }),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 12),
              ],
              TextField(
                controller: _configCtrl,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: "${t('payment.config')}",
                  hintText: '如 {"network":"TRC20"} / {"apm_types":["alipay"]}',
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
