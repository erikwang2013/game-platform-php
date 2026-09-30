// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 优惠券（批次 4）：CRUD + 启停 + 只读统计，全部套 widgets/crud.dart 的底座。
// 字段真值取自 CouponController::create/update 的 validator；金额一律 text 控件原样透传。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class CouponAdminController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/coupon/list');
      items.value = resp['data']['list'] as List<dynamic>? ?? [];
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/coupon/create', data: data);
    await load();
  }

  /// 编辑：已有用户领取的券服务端会拒（400「该优惠券已有用户领取，无法修改」），message 原样显示
  Future<void> updateCoupon(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/coupon/$hashid', data: data);
    await load();
  }

  /// 删除会连带删掉该券的所有用户领取记录（destroy 里先删 user_coupon）
  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/coupon/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }

  /// 启停：没有独立 toggle 端点，走 update 的局部提交（status: `sometimes|required|integer|in:0,1`）。
  /// 同样受「已有用户领取就不能改」的限制（400）。
  Future<void> setStatus(String hashid, int status) async {
    await api.put('/admin/v1/coupon/$hashid', data: <String, dynamic>{'status': status});
    await load();
  }

  /// 只读统计（GET coupon/{hashid}/stats）：total_qty / used_qty / remaining / usage_rate
  Future<Map<String, dynamic>> stats(String hashid) async {
    final resp = await api.get('/admin/v1/coupon/$hashid/stats');
    return Map<String, dynamic>.from(resp['data'] as Map? ?? <String, dynamic>{});
  }
}

class CouponPage extends GetView<CouponAdminController> {
  const CouponPage({super.key});

  /// 字段真值（CouponController::create/update 的 validator + coupon 列定义）：
  /// - name `required|string|max:100`；type `required|in:fixed,rate`
  /// - value `required|numeric|min:0.0001`（fixed=平台币面值，rate=折扣率；两者都是十进制字符串）
  /// - min_amount / max_discount `numeric|min:0`（DECIMAL(18,4)）⇒ text 控件，空 = 不提交
  /// - game_id 是**游戏 hashid**（服务端 decodeId）：新建空 = 全平台（存 0），编辑空 = 保持原值
  /// - total_qty `integer|min:0`（0 = 不限量）、user_limit `integer|min:1`
  /// - start_at / end_at：create **没有格式校验**（写错会撞库报错），update 有 `date` +
  ///   `end_at after_or_equal:start_at`
  /// - status：create 硬编码成 1（不读入参）⇒ 表单里不摆开关，改状态走列表行（局部 PUT）
  /// - conditions（C 端领取的准入条件）**create/update 都不收**（不在 `$request->only()` 里）⇒ 表单里不摆
  static const List<CrudField> _fields = <CrudField>[
    CrudField('name', 'coupon.name', required: true, hint: 'coupon.name_hint'),
    CrudField('type', 'coupon.type', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('fixed', 'coupon.type_fixed'),
      CrudOption('rate', 'coupon.type_rate'),
    ]),
    CrudField('value', 'coupon.value', required: true, hint: 'coupon.value_hint'),
    CrudField('min_amount', 'coupon.min_amount', hint: 'coupon.min_amount_hint'),
    CrudField('max_discount', 'coupon.max_discount', hint: 'coupon.max_discount_hint'),
    CrudField('game_id', 'coupon.game_id', hint: 'coupon.game_id_hint'),
    CrudField('total_qty', 'coupon.total_qty', type: CrudFieldType.number, hint: 'coupon.total_qty_hint'),
    CrudField('user_limit', 'coupon.user_limit', type: CrudFieldType.number, hint: 'coupon.user_limit_hint'),
    CrudField('start_at', 'coupon.start_at', hint: 'coupon.time_hint'),
    CrudField('end_at', 'coupon.end_at', hint: 'coupon.time_hint'),
  ];

  /// 空串在这几列上没有意义（`numeric`/`date` 规则会 422；game_id 空串会被 decodeId 当 0）
  /// ⇒ 提交前去掉：create 回落成后端缺省值，update 保持原值。
  static const Set<String> _omitWhenEmpty = <String>{
    'min_amount', 'max_discount', 'game_id', 'start_at', 'end_at',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<CouponAdminController>()) {
      Get.put(CouponAdminController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('coupon.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) {
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
                DataColumn(label: Text('${AppTranslations.t('coupon.name')}')),
                DataColumn(label: Text('${AppTranslations.t('coupon.type')}')),
                DataColumn(label: Text('${AppTranslations.t('coupon.value')}')),
                DataColumn(label: Text('${AppTranslations.t('coupon.min_amount')}')),
                DataColumn(label: Text('${AppTranslations.t('coupon.max_discount')}')),
                DataColumn(label: Text('${AppTranslations.t('coupon.issued')}')),
                DataColumn(label: Text('${AppTranslations.t('game.status')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
              ],
              rows: ctrl.items.map((c) {
                // 列表里的 id 是 hashid：{hashid} 路径用它
                final id = c['id']?.toString() ?? '';
                final name = c['name']?.toString() ?? '';
                final type = c['type']?.toString() ?? '';
                final status = c['status'] is int ? c['status'] as int : 0;
                final totalQty = c['total_qty']?.toString() ?? '0';
                final usedQty = c['used_qty']?.toString() ?? '0';

                return DataRow(cells: [
                  DataCell(Text(name)),
                  DataCell(Text('${AppTranslations.t(type == 'rate' ? 'coupon.type_rate' : 'coupon.type_fixed')}')),
                  DataCell(Text(c['value']?.toString() ?? '')),
                  DataCell(Text(c['min_amount']?.toString() ?? '')),
                  DataCell(Text(c['max_discount']?.toString() ?? '')),
                  // 总量 0 = 不限量，用 ∞ 的读法而不是「0/0」骗人
                  DataCell(Text(totalQty == '0' ? '$usedQty / ${AppTranslations.t('coupon.unlimited')}' : '$usedQty / $totalQty')),
                  DataCell(Chip(
                    label: Text(status == 1
                        ? '${AppTranslations.t('app.enabled')}'
                        : '${AppTranslations.t('app.disabled')}'),
                    color: WidgetStatePropertyAll(status == 1 ? Colors.green.shade50 : Colors.red.shade50),
                  )),
                  DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                    IconButton(
                      icon: const Icon(Icons.bar_chart, size: 18),
                      tooltip: '${AppTranslations.t('coupon.stats')}',
                      onPressed: () => _showStats(context, ctrl, id, name),
                    ),
                    CrudRowActions(
                      status: status,
                      onToggle: (next) => ctrl.setStatus(id, next),
                      onEdit: () => _openForm(context, ctrl, item: c),
                      // 删除连带删掉所有领取记录 ⇒ 文案里说清楚
                      onDelete: () => confirmCrudAction(
                        context,
                        title: '${crudText('app.confirm')} ${crudText('app.delete')}',
                        message: crudText('coupon.delete_confirm_target', {'name': name}),
                        confirmLabel: crudText('app.delete'),
                        onConfirm: () => ctrl.remove(id),
                      ),
                    ),
                  ])),
                ]);
              }).toList(),
            ),
          ),
        );
      })),
    ]);
  }

  Future<void> _openForm(BuildContext context, CouponAdminController ctrl, {dynamic item}) async {
    final ok = await showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('coupon.create')}' : '${AppTranslations.t('coupon.edit')}',
      fields: _fields,
      initial: item == null ? null : Map<String, dynamic>.from(item as Map),
      onSubmit: (data) {
        data.removeWhere((key, value) => value == '' && _omitWhenEmpty.contains(key));
        return item == null ? ctrl.create(data) : ctrl.updateCoupon(item['id'].toString(), data);
      },
    );
    if (ok) Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> _showStats(BuildContext context, CouponAdminController ctrl, String hashid, String name) async {
    Map<String, dynamic> data;
    try {
      data = await ctrl.stats(hashid);
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', apiErrorMessage(e));
      return;
    }
    if (!context.mounted) return;

    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(crudText('coupon.stats_title', {'name': name})),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          _statRow('coupon.total_qty', data['total_qty'], unlimitedWhenZero: true),
          _statRow('coupon.used_qty', data['used_qty']),
          // remaining 为 null = 不限量（stats 端点对 total_qty=0 的券返回 null）
          _statRow('coupon.remaining', data['remaining'] ?? crudText('coupon.unlimited')),
          _statRow('coupon.usage_rate', data['usage_rate']),
        ]),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: Text(crudText('app.close')))],
      ),
    );
  }

  Widget _statRow(String label, dynamic value, {bool unlimitedWhenZero = false}) {
    final text = unlimitedWhenZero && value?.toString() == '0'
        ? crudText('coupon.unlimited')
        : (value?.toString() ?? '-');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(children: [
        Text(crudText(label), style: const TextStyle(fontWeight: FontWeight.bold)),
        const SizedBox(width: 12),
        Text(text),
      ]),
    );
  }
}
