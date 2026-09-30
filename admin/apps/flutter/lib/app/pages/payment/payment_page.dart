// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 支付方式（批次 4）：CRUD + 启停，全部套 widgets/crud.dart 的底座。
// 金额区间一律 text 控件原样透传（DECIMAL 字符串，浮点控件会吃掉小数位），
// 值域以 PaymentController::create/update 的 validator 为准。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

/// provider 值域：create/update 的 `in:` 列表，一字不差（18 个）。
const List<String> _providers = <String>[
  'stripe',
  'nowpayments',
  'coinbase',
  'paypal',
  'skrill',
  'neteller',
  'paysafecard',
  'paytm',
  'mercadopago',
  'astropay',
  'paypay',
  'kakaopay',
  'gcash',
  'mpesa',
  'paystack',
  'toss',
  'adyen',
  'grabpay',
];

class PaymentController extends GetxController {
  final api = ApiService();
  final methods = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/payment/method/list');
      methods.value = resp['data']['list'] as List<dynamic>? ?? [];
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/payment/method/create', data: data);
    await load();
  }

  Future<void> updateMethod(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/payment/method/$hashid', data: data);
    await load();
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/payment/method/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 启停走独立端点（body 里 id 是 hashid，status 是 0/1）
  Future<void> toggle(String hashid, int status) async {
    await api.post('/admin/v1/payment/method/toggle', data: <String, dynamic>{'id': hashid, 'status': status});
    await load();
  }
}

class PaymentPage extends GetView<PaymentController> {
  const PaymentPage({super.key});

  // provider 下拉由 _providers 展开（for 元素不能进 const 集合）⇒ final 而非 const
  static final List<CrudField> _fields = <CrudField>[
    CrudField('name', 'payment.name', required: true, hint: 'payment.name_hint'),
    CrudField('type', 'payment.type', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('fiat', 'payment.fiat'),
      CrudOption('crypto', 'payment.crypto'),
    ]),
    CrudField('provider', 'payment.provider',
        type: CrudFieldType.select,
        required: true,
        hint: 'payment.provider_hint',
        options: <CrudOption>[for (final p in _providers) CrudOption(p, p)]),
    CrudField('status', 'payment.status', type: CrudFieldType.toggle),
    CrudField('sort', 'payment.sort', type: CrudFieldType.number, hint: 'payment.sort_hint'),
    // countries 提交的是数组（validator: `array`）⇒ 表单按「每行一个国家码」收，提交前拆行（_splitCountries）
    CrudField('countries', 'payment.countries', type: CrudFieldType.multiline, hint: 'payment.countries_hint'),
    CrudField('currency', 'payment.currency', hint: 'payment.currency_hint'),
    // 金额一律 text：DECIMAL 字符串原样进出
    CrudField('min_amount', 'payment.min_amount', hint: 'payment.min_amount_hint'),
    CrudField('max_amount', 'payment.max_amount', hint: 'payment.max_amount_hint'),
    CrudField('config', 'payment.config', type: CrudFieldType.multiline, hint: 'payment.config_hint'),
  ];

  /// 空串在这几个字段上没有意义（`numeric` 规则会 422；config 空 = 清除会与「留空不改」冲突）
  /// ⇒ 提交前去掉：min/max 回落成后端缺省 '0'，config 保持原值。
  static const Set<String> _omitWhenEmpty = <String>{'min_amount', 'max_amount', 'currency', 'config'};

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<PaymentController>()) {
      Get.put(PaymentController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('payment.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.methods.isEmpty) {
          return Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Image.asset('assets/mascot.png', width: 120),
              const SizedBox(height: 12),
              Text("${AppTranslations.t('app.no_data')}"),
            ]),
          );
        }

        return SingleChildScrollView(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: DataTable(
              columns: [
                DataColumn(label: Text('${AppTranslations.t('payment.name')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.type')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.provider')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.currency')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.min_amount')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.max_amount')}')),
                DataColumn(label: Text('${AppTranslations.t('payment.status')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
              ],
              rows: ctrl.methods.map((m) {
                // 列表里的 id 是 hashid：{hashid} 路径与 toggle 的 id 都用它
                final id = m['id']?.toString() ?? '';
                final name = m['name']?.toString() ?? '';
                final type = m['type']?.toString() ?? '';
                final status = m['status'] is int ? m['status'] as int : 0;
                final countries = m['countries'] is List ? (m['countries'] as List) : const <dynamic>[];
                final typeLabel = type == 'fiat'
                    ? 'payment.fiat'
                    : (type == 'crypto' ? 'payment.crypto' : type);

                return DataRow(cells: [
                  DataCell(Text(name)),
                  DataCell(Text('${AppTranslations.t(typeLabel)}')),
                  DataCell(Text(m['provider']?.toString() ?? '')),
                  DataCell(Text(countries.isEmpty
                      ? '${AppTranslations.t('payment.global')}'
                      : countries.join(', '))),
                  DataCell(Text(m['min_amount']?.toString() ?? '')),
                  DataCell(Text(m['max_amount']?.toString() ?? '')),
                  DataCell(Chip(
                    label: Text(status == 1
                        ? '${AppTranslations.t('app.enabled')}'
                        : '${AppTranslations.t('app.disabled')}'),
                    color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                  )),
                  DataCell(CrudRowActions(
                    status: status,
                    onToggle: (next) => ctrl.toggle(id, next),
                    onEdit: () => _openForm(context, ctrl, method: m),
                    onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
                  )),
                ]);
              }).toList(),
            ),
          ),
        );
      })),
    ]);
  }

  /// 新建态的 status 初值给 0（禁用）：网关配置没核过就不该对用户可见，
  /// 配好之后在列表行里点启用（= 与 CDN 厂商同款的安全默认值）。
  Future<void> _openForm(BuildContext context, PaymentController ctrl, {dynamic method}) async {
    final initial = method == null
        ? <String, dynamic>{'status': 0}
        : Map<String, dynamic>.from(method as Map);
    if (method != null) {
      // 行里的 countries 是数组（cast 成 array），表单收的是「每行一个」的文本
      final countries = method['countries'];
      initial['countries'] = countries is List ? countries.join('\n') : '';
    }

    final ok = await showCrudForm(
      context,
      title: method == null
          ? '${AppTranslations.t('payment.create_title')}'
          : '${AppTranslations.t('payment.edit_title')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) {
        data['countries'] = _splitCountries(data['countries']?.toString() ?? '');
        data.removeWhere((key, value) => value == '' && _omitWhenEmpty.contains(key));
        return method == null ? ctrl.create(data) : ctrl.updateMethod(method['id'].toString(), data);
      },
    );
    if (ok) Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 「每行一个国家码」（逗号/空格也当分隔符）→ 大写后的数组。
  /// 大写是为了与 C 端 `isAvailableIn()` 的精确比较对齐（库里存的都是 ISO 3166-1 alpha-2 大写）；
  /// 空 = 全球可见（后端 isAvailableIn 把空数组当不限制）。
  static List<String> _splitCountries(String text) => text
      .split(RegExp(r'[\s,，]+'))
      .map((code) => code.trim().toUpperCase())
      .where((code) => code.isNotEmpty)
      .toList();
}
