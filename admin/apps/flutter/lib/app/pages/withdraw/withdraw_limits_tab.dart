// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 阶梯限额（提现页第二个页签）：**只改，不增删** —— 档位行（default/verified/vip）是 install.sql
// 预置的，路由表也只有 list / set / update 三个端点。
// 金额/费率一律 text 控件原样透传：DECIMAL(18,4) 的字符串进出，浮点控件会吃掉小数位。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class WithdrawLimitController extends GetxController {
  final api = ApiService();
  final tiers = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/withdraw/limits/list');
      tiers.value = resp['data']['list'] as List<dynamic>? ?? [];
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  /// 单档精调（PUT {hashid}，WithdrawController::updateLimit）：返回服务端 message
  /// （单笔下限高于上限时是 422 原话「单笔最低不得高于单笔最高」）。
  Future<String> saveLimit(String hashid, Map<String, dynamic> data) async {
    final resp = await api.put('/admin/v1/withdraw/limits/$hashid', data: data);
    await load();
    return resp['message']?.toString() ?? '';
  }

  /// 全档位重置（POST limits/set，WithdrawController::setLimits）：把
  /// daily_limit / min_amount(→各档 single_min) / auto_approve_threshold 一把写穿到**所有**档位行
  /// 与平台配置。返回服务端 message —— 任一档位自相矛盾时整笔不生效，原话在 message 里。
  Future<String> resetAll(Map<String, dynamic> data) async {
    final resp = await api.post('/admin/v1/withdraw/limits/set', data: data);
    await load();
    return resp['message']?.toString() ?? '';
  }
}

class WithdrawLimitsTab extends GetView<WithdrawLimitController> {
  const WithdrawLimitsTab({super.key});

  /// 字段真值取自 updateLimit / setLimits 的 validator：7 个金额/费率字段全是
  /// `nullable|numeric|min:0`（fee_pct 另有 `lt:100`）⇒ 一律 text，空 = 不提交 = 保持原值。
  static const List<CrudField> _tierFields = <CrudField>[
    CrudField('user_level', 'withdraw.limit_level', editableOnEdit: false, hint: 'withdraw.limit_level_hint'),
    CrudField('single_min', 'withdraw.single_min', hint: 'withdraw.single_min_hint'),
    CrudField('single_max', 'withdraw.single_max', hint: 'withdraw.single_max_hint'),
    CrudField('daily_limit', 'withdraw.daily_limit'),
    CrudField('monthly_limit', 'withdraw.monthly_limit'),
    CrudField('fee_pct', 'withdraw.fee_pct', hint: 'withdraw.fee_pct_hint'),
    CrudField('fee_max', 'withdraw.fee_max', hint: 'withdraw.fee_max_hint'),
    CrudField('auto_approve_threshold', 'withdraw.auto_threshold', hint: 'withdraw.auto_threshold_hint'),
  ];

  /// 全档位重置的三个入参（就是 setLimits 读的三个键，min_amount 在库里落 single_min 列）。
  static const List<CrudField> _resetFields = <CrudField>[
    CrudField('daily_limit', 'withdraw.daily_limit', hint: 'withdraw.reset_hint'),
    CrudField('min_amount', 'withdraw.min_amount', hint: 'withdraw.reset_hint'),
    CrudField('auto_approve_threshold', 'withdraw.auto_threshold', hint: 'withdraw.reset_hint'),
  ];

  /// 空串在这 7 列上没有意义（`numeric` 规则会 422；fill 空值也不是「清零」）⇒ 提交前去掉。
  static const Set<String> _omitWhenEmpty = <String>{
    'single_min', 'single_max', 'daily_limit', 'monthly_limit', 'fee_pct', 'fee_max', 'auto_approve_threshold',
  };

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<WithdrawLimitController>()) {
      Get.put(WithdrawLimitController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 12),
      Row(children: [
        Text('${AppTranslations.t('withdraw.limits_title')}', style: Theme.of(context).textTheme.titleMedium),
        const Spacer(),
        OutlinedButton.icon(
          icon: const Icon(Icons.restart_alt, size: 18),
          label: Text('${AppTranslations.t('withdraw.reset_all')}'),
          onPressed: () => _openReset(context, ctrl),
        ),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.tiers.isEmpty) {
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
                DataColumn(label: Text('${AppTranslations.t('withdraw.limit_level')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.single_min')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.single_max')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.daily_limit')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.monthly_limit')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.fee_pct')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.fee_max')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.auto_threshold')}')),
                DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
              ],
              rows: ctrl.tiers.map((tier) {
                final level = tier['user_level']?.toString() ?? '';
                String cell(String key) => tier[key]?.toString() ?? '';

                return DataRow(cells: [
                  DataCell(Text(level)),
                  DataCell(Text(cell('single_min'))),
                  DataCell(Text(cell('single_max'))),
                  DataCell(Text(cell('daily_limit'))),
                  DataCell(Text(cell('monthly_limit'))),
                  DataCell(Text(cell('fee_pct'))),
                  DataCell(Text(cell('fee_max'))),
                  DataCell(Text(cell('auto_approve_threshold'))),
                  DataCell(CrudRowActions(
                    // 没有 toggle 端点、档位也不该被停用/删除 ⇒ 只剩编辑
                    onEdit: () => _openTierForm(context, ctrl, tier),
                  )),
                ]);
              }).toList(),
            ),
          ),
        );
      })),
    ]);
  }

  Future<void> _openTierForm(BuildContext context, WithdrawLimitController ctrl, dynamic tier) async {
    final level = tier['user_level']?.toString() ?? '';
    final ok = await showCrudForm(
      context,
      title: crudText('withdraw.tier_edit_title', {'level': level}),
      fields: _tierFields,
      initial: Map<String, dynamic>.from(tier as Map),
      onSubmit: (data) async {
        data.removeWhere((key, value) => value == '' && _omitWhenEmpty.contains(key));
        await ctrl.saveLimit(tier['id'].toString(), data);
      },
    );
    if (ok) Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> _openReset(BuildContext context, WithdrawLimitController ctrl) async {
    var message = '';
    final ok = await showCrudForm(
      context,
      title: '${AppTranslations.t('withdraw.reset_all')}',
      fields: _resetFields,
      onSubmit: (data) async {
        data.removeWhere((key, value) => value == '');
        if (data.isEmpty) return; // 三个都空 = 什么都没提交（setLimits 对缺省键是「不动」）⇒ 也不报成功
        // 一次写穿 default/verified/vip 所有档位行，且带 auto_approve_threshold（自动放款阈值＝钱路）
        // ⇒ 危险动作，确认文案必须点出**范围**（三档全改）与**将写入的三个值**，空的那项写明保持原值。
        final keep = crudText('withdraw.reset_keep');
        final confirmed = await confirmCrudAction(
          context,
          title: '${crudText('app.confirm')} ${crudText('withdraw.reset_all')}',
          message: crudText('withdraw.reset_confirm', {
            'daily': data['daily_limit']?.toString() ?? keep,
            'min': data['min_amount']?.toString() ?? keep,
            'auto': data['auto_approve_threshold']?.toString() ?? keep,
          }),
          confirmLabel: crudText('withdraw.reset_all'),
          onConfirm: () async {
            message = await ctrl.resetAll(data);
          },
        );
        if (!confirmed) return; // 取消 ⇒ 一个字节都不发（表单随后关闭，与审核流程同）
      },
    );
    if (ok && message.isNotEmpty) Get.snackbar('${AppTranslations.t('app.success')}', message);
  }
}
