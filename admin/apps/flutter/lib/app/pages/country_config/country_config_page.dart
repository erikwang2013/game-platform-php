// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class CountryConfigAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;

  /// 与后端缺省一致（CountryConfigController::index 的 `input('limit', 15)`）。
  static const int pageSize = 15;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final result = await api.list('/admin/v1/country/config/list', page: page.value, pageSize: pageSize);
      items.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/country/config/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateConfig(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/country/config/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/country/config/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 状态切换走独立端点（body 里 id 是 hashid），与 update 传 status 的不是同一条路
  Future<void> toggle(String hashid, int status) async {
    await api.post('/admin/v1/country/config/toggle', data: <String, dynamic>{'id': hashid, 'status': status});
    await load();
  }
}

class CountryConfigPage extends GetView<CountryConfigAdminController> {
  const CountryConfigPage({super.key});

  /// 字段真值取自 CountryConfigController::create/update/toggle 的 validator + game_country_config 列定义：
  /// - country_code：create `required|string|size:2`（服务端 strtoupper），**update 的规则里没有** ⇒
  ///   编辑态置灰且不提交；列上有 uk_country_code 唯一键
  /// - currency：`required|string|size:3`（update 同款）
  /// - min_deposit：列是 DECIMAL(18,4) NOT NULL ⇒ 用文本控件原样透传（number 控件会 int 化，把 1.0000 变成 1），
  ///   且空串不提交（见 _openForm）
  /// - payment_methods/withdraw_methods：TEXT 里的 JSON 规则文本，空串同样不提交
  /// - status：**表单里不放**——create 恒写 `status = 1`、不读入参，状态改由列表行开关走 toggle 端点
  static const List<CrudField> _fields = <CrudField>[
    CrudField('country_code', 'country_config.country_code', required: true,
        editableOnEdit: false, hint: 'country_config.country_code_hint'),
    CrudField('currency', 'country_config.currency', required: true, hint: 'country_config.currency_hint'),
    CrudField('min_deposit', 'country_config.min_deposit', hint: 'country_config.min_deposit_hint'),
    CrudField('payment_methods', 'country_config.payment_methods',
        type: CrudFieldType.multiline, hint: 'country_config.methods_hint'),
    CrudField('withdraw_methods', 'country_config.withdraw_methods',
        type: CrudFieldType.multiline, hint: 'country_config.methods_hint'),
  ];

  /// 空串在这三列上没有意义：min_deposit 是 DECIMAL NOT NULL、两个 *_methods 是 JSON 文本。
  /// validator 的 `nullable` 只对 null 放行（空串会跳过所有非 implicit 规则），拦不住写入 ⇒ 在提交口去掉。
  static const Set<String> _omitWhenEmpty = <String>{'min_deposit', 'payment_methods', 'withdraw_methods'};

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<CountryConfigAdminController>()) {
      Get.put(CountryConfigAdminController(), permanent: false);
    }
    final ctrl = controller;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CrudHeader(
          title: "${AppTranslations.t('country_config.title')}",
          onCreate: () => _openForm(context, ctrl),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: Obx(() {
            if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
            if (ctrl.items.isEmpty) {
              return const CrudEmptyState();
            }

            return SingleChildScrollView(
              child: DataTable(
                columns: [
                  DataColumn(label: Text("${AppTranslations.t('country_config.country_code')}")),
                  DataColumn(label: Text("${AppTranslations.t('country_config.currency')}")),
                  DataColumn(label: Text("${AppTranslations.t('country_config.min_deposit')}")),
                  DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('game.actions')}')),
                ],
                rows: ctrl.items.map((c) {
                  // 列表里的 id 是 hashid：{hashid} 路径与 toggle 的 id 都用它
                  final id = c['id']?.toString() ?? '';
                  final code = c['country_code']?.toString() ?? '';
                  final currency = c['currency']?.toString() ?? '';
                  final minDeposit = c['min_deposit']?.toString() ?? '';
                  final status = c['status'] is int ? c['status'] as int : 0;

                  return DataRow(cells: [
                    DataCell(Text(code)),
                    DataCell(Text(currency)),
                    DataCell(Text(minDeposit)),
                    DataCell(Chip(
                      label: Text(status == 1 ? '${AppTranslations.t('app.enabled')}' : '${AppTranslations.t('app.disabled')}'),
                      color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                    )),
                    DataCell(CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.toggle(id, next),
                      onEdit: () => _openForm(context, ctrl, item: c),
                      onDelete: () => confirmCrudDelete(context, what: code, onConfirm: () => ctrl.remove(id)),
                    )),
                  ]);
                }).toList(),
              ),
            );
          }),
        ),
        const SizedBox(height: 8),
        Obx(() => CrudPager(
              page: ctrl.page.value,
              total: ctrl.total.value,
              size: CountryConfigAdminController.pageSize,
              onPage: (p) => ctrl.load(toPage: p),
            )),
      ],
    );
  }

  Future<void> _openForm(BuildContext context, CountryConfigAdminController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('country_config.create')}' : '${AppTranslations.t('country_config.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) {
        data.removeWhere((key, value) => value == '' && _omitWhenEmpty.contains(key));
        return item == null ? ctrl.create(data) : ctrl.updateConfig(item['id'].toString(), data);
      },
    );
  }
}
