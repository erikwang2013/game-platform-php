// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 提现管理（批次 4）：提现订单的审核 / 打款执行 / 打款同步 + 全局开关；阶梯限额在第二个页签。
// 写操作一律套 widgets/crud.dart 的底座：审核走 showCrudForm（动作 + 备注）再 confirmCrudAction，
// 打款动作直接 confirmCrudAction —— 确认文案必带**订单号与金额**，成功与否以服务端 message 为准
// （不做乐观更新，也不在前端推断打款状态）。
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../i18n/translations.dart';
import '../../widgets/crud.dart';
import 'withdraw_controller.dart';
import 'withdraw_limits_tab.dart';

// 控制器与订单文案助手在 withdraw_controller.dart —— 转出去，调用方 import 本文件即全拿到
export 'withdraw_controller.dart';

class WithdrawPage extends GetView<WithdrawController> {
  const WithdrawPage({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<WithdrawController>()) {
      Get.put(WithdrawController(), permanent: false);
    }

    return DefaultTabController(
      length: 2,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Text('${AppTranslations.t('withdraw.title')}',
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Spacer(),
            // 全局开关：GET/PUT 同一个端点，读数与写数都取服务端
            Obx(() => Row(mainAxisSize: MainAxisSize.min, children: [
                  Text('${AppTranslations.t('withdraw.global_switch')}', style: const TextStyle(fontSize: 13)),
                  const SizedBox(width: 8),
                  Switch(
                    value: controller.withdrawEnabled.value,
                    onChanged: (v) => controller.setSwitch(v ? 1 : 0),
                  ),
                ])),
          ]),
          const SizedBox(height: 8),
          TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              Tab(text: '${AppTranslations.t('withdraw.orders')}'),
              Tab(text: '${AppTranslations.t('withdraw.limits')}'),
            ],
          ),
          Expanded(
            child: TabBarView(children: [
              _ordersTab(context),
              const WithdrawLimitsTab(),
            ]),
          ),
        ],
      ),
    );
  }

  Widget _ordersTab(BuildContext context) {
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SizedBox(height: 12),
      Obx(() => SegmentedButton<String>(
            segments: [
              ButtonSegment(value: 'all', label: Text('${AppTranslations.t('withdraw.all')}')),
              ButtonSegment(value: 'pending', label: Text('${AppTranslations.t('withdraw.pending')}')),
              ButtonSegment(value: 'approved', label: Text('${AppTranslations.t('withdraw.approved')}')),
              ButtonSegment(value: 'processing', label: Text('${AppTranslations.t('withdraw.processing')}')),
              ButtonSegment(value: 'rejected', label: Text('${AppTranslations.t('withdraw.rejected')}')),
            ],
            selected: {ctrl.statusFilter.value},
            onSelectionChanged: (v) {
              ctrl.statusFilter.value = v.first;
              ctrl.page.value = 1; // 换筛选回第 1 页（勾选集在 loadOrders 里已被清空）
              ctrl.loadOrders();
            },
          )),
      const SizedBox(height: 12),
      Obx(() => Row(children: [
            Text(crudText('withdraw.selected', {'n': '${ctrl.selected.length}'}),
                style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(width: 12),
            OutlinedButton(
              onPressed: ctrl.selected.isEmpty
                  ? null
                  : () => _reviewFlow(context, ctrl, ctrl.selectedOrders(), 'approve'),
              child: Text('${AppTranslations.t('withdraw.batch_approve')}'),
            ),
            const SizedBox(width: 8),
            OutlinedButton(
              onPressed: ctrl.selected.isEmpty
                  ? null
                  : () => _reviewFlow(context, ctrl, ctrl.selectedOrders(), 'reject'),
              child: Text('${AppTranslations.t('withdraw.batch_reject')}'),
            ),
          ])),
      const SizedBox(height: 12),
      Expanded(
        child: Obx(() {
          if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
          if (ctrl.orders.isEmpty) {
            return const CrudEmptyState();
          }

          return SingleChildScrollView(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: DataTable(
                showCheckboxColumn: true,
                columns: [
                  DataColumn(label: Text('${AppTranslations.t('withdraw.order_no')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.user')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.amount')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.status')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.payout_status')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.submit_time')}')),
                  DataColumn(label: Text('${AppTranslations.t('withdraw.actions')}')),
                ],
                rows: ctrl.orders.map((o) {
                  final id = o['id']?.toString() ?? '';
                  final status = o['status']?.toString() ?? '';
                  final payoutStatus = o['payout_status']?.toString().trim() ?? '';
                  final reviewerId = int.tryParse(o['reviewer_id']?.toString() ?? '') ?? 0;
                  final note = o['review_note']?.toString().trim() ?? '';
                  final user = o['user'];

                  Color statusColor;
                  switch (status) {
                    case 'approved':
                    case 'completed':
                      statusColor = Colors.green;
                    case 'rejected':
                      statusColor = Colors.red;
                    case 'processing':
                      statusColor = Colors.blue;
                    default:
                      statusColor = Colors.orange;
                  }

                  return DataRow(
                    selected: ctrl.selected.contains(id),
                    onSelectChanged: (on) => on == true ? ctrl.selected.add(id) : ctrl.selected.remove(id),
                    cells: [
                      DataCell(Text(withdrawOrderLabel(o))),
                      DataCell(Text(user is Map ? (user['username']?.toString() ?? '') : '')),
                      DataCell(Text(o['platform_amount']?.toString() ?? '')),
                      DataCell(Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Chip(
                            label: Text('${AppTranslations.t(withdrawStatusLabels[status] ?? status)}',
                                style: TextStyle(color: statusColor, fontSize: 12)),
                            backgroundColor: statusColor.withValues(alpha: 0.1),
                          ),
                          if (note.isNotEmpty)
                            SizedBox(
                              width: 160,
                              child: Text(note,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodySmall),
                            ),
                        ],
                      )),
                      DataCell(Text(payoutStatus.isEmpty
                          ? '-'
                          : '${AppTranslations.t(withdrawPayoutLabels[payoutStatus] ?? payoutStatus)}')),
                      DataCell(Text(o['created_at']?.toString() ?? '')),
                      DataCell(_rowActions(context, ctrl, o,
                          status: status, reviewerId: reviewerId, payoutStatus: payoutStatus)),
                    ],
                  );
                }).toList(),
              ),
            ),
          );
        }),
      ),
      const SizedBox(height: 8),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: WithdrawController.pageSize,
            onPage: (p) => ctrl.loadOrders(toPage: p),
          )),
    ]);
  }

  /// 行内动作按状态机过滤（WithdrawReviewTrait 的 CAS 条件），不摆点了必然 422 的按钮：
  /// pending + reviewer_id=0 → 通过/驳回；pending + reviewer_id>0 → 二次确认/驳回；
  /// approved → 执行打款；有批次号 → 同步打款。
  Widget _rowActions(
    BuildContext context,
    WithdrawController ctrl,
    dynamic order, {
    required String status,
    required int reviewerId,
    required String payoutStatus,
  }) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      if (status == 'pending' && reviewerId <= 0)
        IconButton(
          icon: const Icon(Icons.check, color: Colors.green, size: 20),
          tooltip: '${AppTranslations.t('withdraw.approve')}',
          onPressed: () => _reviewFlow(context, ctrl, [order], 'approve'),
        ),
      if (status == 'pending' && reviewerId > 0)
        IconButton(
          icon: const Icon(Icons.verified, color: Colors.green, size: 20),
          tooltip: '${AppTranslations.t('withdraw.second_confirm')}',
          onPressed: () => _reviewFlow(context, ctrl, [order], 'confirm'),
        ),
      if (status == 'pending')
        IconButton(
          icon: const Icon(Icons.close, color: Colors.red, size: 20),
          tooltip: '${AppTranslations.t('withdraw.reject')}',
          onPressed: () => _reviewFlow(context, ctrl, [order], 'reject'),
        ),
      if (status == 'approved')
        IconButton(
          icon: const Icon(Icons.payments_outlined, color: Colors.green, size: 20),
          tooltip: '${AppTranslations.t('withdraw.execute')}',
          onPressed: () => _payoutFlow(context, ctrl, order),
        ),
      if (payoutStatus.isNotEmpty)
        IconButton(
          icon: const Icon(Icons.sync, size: 20),
          tooltip: '${AppTranslations.t('withdraw.sync')}',
          onPressed: () => _syncFlow(context, ctrl, order),
        ),
    ]);
  }

  /// 审核流程：表单（动作 + 备注）→ 二次确认（文案带订单号与金额）→ 读服务端 message。
  /// 单条与批量共用：批量只多传几行、动作下拉少一个 confirm（batch-review 不收）。
  Future<void> _reviewFlow(
    BuildContext context,
    WithdrawController ctrl,
    List<dynamic> rows,
    String preset,
  ) async {
    if (rows.isEmpty) return;
    final ids = <String>[for (final row in rows) row['id'].toString()];
    final isBatch = rows.length > 1;

    await showCrudForm(
      context,
      title: isBatch
          ? crudText('withdraw.batch_title', {'n': '${rows.length}'})
          : crudText('withdraw.review_title', {'no': withdrawOrderLabel(rows.single)}),
      fields: _reviewFields(isBatch),
      initial: <String, dynamic>{'action': preset},
      onSubmit: (data) async {
        final action = data['action'].toString();
        final note = data['note']?.toString() ?? '';
        final message = <String>[''];

        final ok = await confirmCrudAction(
          context,
          title: '${crudText('app.confirm')} ${crudText(_actionLabels[action] ?? action)}',
          message: isBatch
              ? crudText('withdraw.batch_confirm', {
                  'action': crudText(_actionLabels[action] ?? action),
                  'n': '${rows.length}',
                  'amount': withdrawSumPlatformAmount(rows),
                  'ids': ids.join(', '),
                })
              : crudText(_confirmMessages[action] ?? 'app.confirm', {
                  'no': withdrawOrderLabel(rows.single),
                  'amount': withdrawOrderMoney(rows.single),
                }),
          confirmLabel: crudText(_actionLabels[action] ?? action),
          onConfirm: () async {
            message[0] = isBatch
                ? await ctrl.batchReview(ids, action, note)
                : await ctrl.review(ids.single, action, note);
          },
        );
        if (ok) Get.snackbar('${AppTranslations.t('app.success')}', message[0]);
      },
    );
  }

  /// 打款执行：真正出钱的一步，二次确认文案带订单号 + 金额；结果读服务端 message
  /// （「打款成功」/「打款已提交」，失败时服务端会退回 approved 允许重试）。
  Future<void> _payoutFlow(BuildContext context, WithdrawController ctrl, dynamic order) async {
    final message = <String>[''];
    final ok = await confirmCrudAction(
      context,
      title: '${crudText('app.confirm')} ${crudText('withdraw.execute')}',
      message: crudText('withdraw.execute_confirm', {
        'no': withdrawOrderLabel(order),
        'amount': withdrawOrderMoney(order),
      }),
      confirmLabel: crudText('withdraw.execute'),
      onConfirm: () async {
        message[0] = await ctrl.executePayout(order['id'].toString());
      },
    );
    if (ok) Get.snackbar('${AppTranslations.t('app.success')}', message[0]);
  }

  /// 打款同步：从 PayPal 回读状态（外部副作用，不可撤销），同样先确认。
  /// 显示的是 data 里的三个状态（该端点的 message 是占位「success」，没有信息量）。
  Future<void> _syncFlow(BuildContext context, WithdrawController ctrl, dynamic order) async {
    final data = <Map<String, dynamic>>[<String, dynamic>{}];
    final ok = await confirmCrudAction(
      context,
      title: '${crudText('app.confirm')} ${crudText('withdraw.sync')}',
      message: crudText('withdraw.sync_confirm', {
        'no': withdrawOrderLabel(order),
        'amount': withdrawOrderMoney(order),
      }),
      confirmLabel: crudText('withdraw.sync'),
      onConfirm: () async {
        data[0] = await ctrl.syncPayout(order['id'].toString());
      },
    );
    if (ok) {
      Get.snackbar(
        '${AppTranslations.t('app.success')}',
        crudText('withdraw.sync_result', {
          'payout': data[0]['payout_status']?.toString() ?? '-',
          'order': data[0]['order_status']?.toString() ?? '-',
          'synced': data[0]['synced_status']?.toString() ?? '-',
        }),
      );
    }
  }

  /// 审核表单字段：note 是三个动作共用的入参（服务端 $request->input('note')）。
  /// 批量审核没有 confirm 动作（batch-review 的 validator 只有 approve|reject）。
  static List<CrudField> _reviewFields(bool isBatch) => <CrudField>[
        CrudField('action', 'withdraw.action',
            type: CrudFieldType.select,
            required: true,
            options: <CrudOption>[
              CrudOption('approve', 'withdraw.approve'),
              CrudOption('reject', 'withdraw.reject'),
              if (!isBatch) CrudOption('confirm', 'withdraw.second_confirm'),
            ]),
        CrudField('note', 'withdraw.note', type: CrudFieldType.multiline, hint: 'withdraw.note_hint'),
      ];

  static const Map<String, String> _actionLabels = <String, String>{
    'approve': 'withdraw.approve',
    'reject': 'withdraw.reject',
    'confirm': 'withdraw.second_confirm',
  };

  /// 二次确认文案：三种动作各自说清后果（通过=可打款、驳回=立即退款不可撤销、确认=双审放行）。
  static const Map<String, String> _confirmMessages = <String, String>{
    'approve': 'withdraw.approve_confirm',
    'reject': 'withdraw.reject_confirm',
    'confirm': 'withdraw.confirm_confirm',
  };
}
