/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class TicketController extends GetxController {
  final api = ApiService();
  final tickets = <dynamic>[].obs;
  final isLoading = false.obs;
  final statusFilter = 'all'.obs;
  final total = 0.obs;
  final page = 1.obs;

  /// 与后端缺省一致（TicketController::index 的 `input('limit', 20)`）。
  static const int pageSize = 20;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final params = <String, dynamic>{};
      if (statusFilter.value != 'all') params['status'] = statusFilter.value;
      final result = await api.list('/admin/v1/ticket/list',
          page: page.value, pageSize: pageSize, params: params);
      tickets.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 三个动作各有专用端点（TicketController::reply/close/assign）——**不吞异常**，
  // 失败信息（如「已关闭的工单不能回复」的 422）冒到表单/确认框里。

  Future<void> reply(String hashid, String content) async {
    await api.post('/admin/v1/ticket/$hashid/reply', data: <String, dynamic>{'content': content});
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('ticket.replied')}');
  }

  Future<void> close(String hashid) async {
    await api.post('/admin/v1/ticket/$hashid/close');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('ticket.closed')}');
  }

  /// assign 的 admin_id 是**数字主键**（`(int) $request->input('admin_id', 0)`），不是 hashid。
  Future<void> assign(String hashid, int adminId) async {
    await api.post('/admin/v1/ticket/$hashid/assign', data: <String, dynamic>{'admin_id': adminId});
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('ticket.assigned')}');
  }

  Future<Map<String, dynamic>?> detail(String hashid) async {
    try {
      final resp = await api.get('/admin/v1/ticket/$hashid');
      return resp['data'] as Map<String, dynamic>?;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
      return null;
    }
  }
}

class TicketPage extends GetView<TicketController> {
  const TicketPage({super.key});

  /// 工单是「动作型」模块（列表由 C 端产生，管理端只能回复/关闭/指派），
  /// 底座的 CrudHeader（需要 onCreate）与 CrudRowActions（状态开关/编辑/删除）都不适用 ——
  /// 这里只复用它的两个动作原语：showCrudForm（回复正文、受理人）与 confirmCrudAction（关闭）。
  static const List<CrudField> _replyFields = <CrudField>[
    // TicketController::reply：content 为空即 422（服务端不收空回复）
    CrudField('content', 'ticket.reply_content', type: CrudFieldType.multiline, required: true, hint: 'ticket.reply_hint'),
  ];

  static const List<CrudField> _assignFields = <CrudField>[
    CrudField('admin_id', 'ticket.admin_id', type: CrudFieldType.number, required: true, hint: 'ticket.admin_id_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<TicketController>()) {
      Get.put(TicketController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text("${AppTranslations.t('ticket.title')}", style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      const SizedBox(height: 12),
      // 状态值域取自 game_ticket.status（install/clickhouse.sql:37 open/waiting/replied/closed）
      Obx(() => SegmentedButton<String>(
        segments: [
          ButtonSegment(value: 'all', label: Text('${AppTranslations.t('ticket.all')}')),
          ButtonSegment(value: 'open', label: Text('${AppTranslations.t('ticket.status_open')}')),
          ButtonSegment(value: 'waiting', label: Text('${AppTranslations.t('ticket.status_waiting')}')),
          ButtonSegment(value: 'replied', label: Text('${AppTranslations.t('ticket.status_replied')}')),
          ButtonSegment(value: 'closed', label: Text('${AppTranslations.t('ticket.status_closed')}')),
        ],
        selected: {ctrl.statusFilter.value},
        onSelectionChanged: (v) {
          ctrl.statusFilter.value = v.first;
          ctrl.page.value = 1; // 换状态筛选回第 1 页，否则停在第 3 页看「已关闭」多半是空的
          ctrl.load();
        },
      )),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.tickets.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/mascot.png', width: 120),
                const SizedBox(height: 12),
                Text("${AppTranslations.t('ticket.no_data')}"),
              ],
            ),
          );
        }

        return SingleChildScrollView(
          child: DataTable(
            columns: [
              DataColumn(label: Text('${AppTranslations.t('ticket.subject')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.user')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.type')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.status')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.replies')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.created')}')),
              DataColumn(label: Text('${AppTranslations.t('ticket.actions')}')),
            ],
            rows: ctrl.tickets.map((t) {
              final id = t['id']?.toString() ?? '';
              final subject = t['subject']?.toString() ?? '';
              final status = t['status']?.toString() ?? '';
              return DataRow(
                onSelectChanged: (_) => _showDetail(context, ctrl, t),
                cells: [
                  DataCell(Text(subject)),
                  DataCell(Text(t['user_name']?.toString() ?? '')),
                  DataCell(Text(t['type']?.toString() ?? '')),
                  DataCell(Chip(
                    label: Text(_statusLabel(status)),
                    color: WidgetStatePropertyAll(status == 'closed'
                        ? Colors.grey.shade200
                        : (status == 'replied' ? Colors.green.shade50 : Colors.orange.shade50)),
                  )),
                  DataCell(Text('${t['reply_count'] ?? 0}')),
                  DataCell(Text(t['created_at']?.toString() ?? '')),
                  DataCell(Row(mainAxisSize: MainAxisSize.min, children: [
                    IconButton(
                      icon: const Icon(Icons.reply, size: 18),
                      tooltip: '${AppTranslations.t('ticket.reply')}',
                      // 已关闭的工单回复会 422，服务端 message 直接在框里显示
                      onPressed: () => _openReply(context, ctrl, id, subject),
                    ),
                    IconButton(
                      icon: const Icon(Icons.person_add_alt, size: 18),
                      tooltip: '${AppTranslations.t('ticket.assign')}',
                      onPressed: () => _openAssign(context, ctrl, id, subject),
                    ),
                    IconButton(
                      icon: const Icon(Icons.lock_outline, size: 18, color: Colors.red),
                      tooltip: '${AppTranslations.t('ticket.close')}',
                      onPressed: () => confirmCrudAction(
                        context,
                        title: '${crudText('app.confirm')} ${crudText('ticket.close')}',
                        message: crudText('ticket.close_confirm_target', {'name': subject}),
                        confirmLabel: crudText('ticket.close'),
                        onConfirm: () => ctrl.close(id),
                      ),
                    ),
                  ])),
                ],
              );
            }).toList(),
          ),
        );
      })),
      const SizedBox(height: 8),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: TicketController.pageSize,
            onPage: (p) => ctrl.load(toPage: p),
          )),
    ]);
  }

  Future<void> _openReply(BuildContext context, TicketController ctrl, String id, String subject) {
    return showCrudForm(
      context,
      title: crudText('ticket.reply_title', {'name': subject}),
      fields: _replyFields,
      onSubmit: (data) => ctrl.reply(id, data['content'].toString()),
    );
  }

  Future<void> _openAssign(BuildContext context, TicketController ctrl, String id, String subject) {
    return showCrudForm(
      context,
      title: crudText('ticket.assign_title', {'name': subject}),
      fields: _assignFields,
      onSubmit: (data) => ctrl.assign(id, int.tryParse(data['admin_id'].toString()) ?? 0),
    );
  }

  /// 只看对话：动作按钮留在行上（回复要正文、指派要受理人，都得先看一眼上下文）。
  void _showDetail(BuildContext context, TicketController ctrl, dynamic ticket) async {
    final detail = await ctrl.detail(ticket['id'].toString());
    if (detail == null || !context.mounted) return;

    final replies = detail['replies'] as List<dynamic>? ?? const [];
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('${AppTranslations.t('ticket.detail')} - ${detail['subject'] ?? ticket['subject']}'),
        content: SizedBox(
          width: 520,
          child: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(detail['content'] ?? '', style: const TextStyle(fontSize: 13)),
              const Divider(),
              if (replies.isEmpty)
                Text("${AppTranslations.t('app.no_data')}", style: const TextStyle(fontSize: 13)),
              for (final r in replies)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(
                      '${r['is_admin'] == 1 ? AppTranslations.t('ticket.admin') : AppTranslations.t('ticket.customer')} · ${r['created_at'] ?? ''}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                    Text(r['content']?.toString() ?? '', style: const TextStyle(fontSize: 13)),
                  ]),
                ),
            ]),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text('${AppTranslations.t('app.close')}')),
        ],
      ),
    );
  }

  static String _statusLabel(String status) {
    switch (status) {
      case 'open':
        return '${AppTranslations.t('ticket.status_open')}';
      case 'waiting':
        return '${AppTranslations.t('ticket.status_waiting')}';
      case 'replied':
        return '${AppTranslations.t('ticket.status_replied')}';
      case 'closed':
        return '${AppTranslations.t('ticket.status_closed')}';
      default:
        return status;
    }
  }
}
