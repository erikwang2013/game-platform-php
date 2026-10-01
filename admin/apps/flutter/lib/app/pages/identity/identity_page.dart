// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../i18n/translations.dart';
import '../../widgets/crud.dart';

class IdentityController extends GetxController {
  final api = ApiService();
  final list = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;

  /// 与后端缺省一致（IdentityController::index 的 `input('limit', 15)`）。
  static const int pageSize = 15;
  String statusFilter = 'pending';

  @override
  void onInit() { super.onInit(); loadData(); }

  Future<void> loadData({int? toPage}) async {
    if (toPage != null) page.value = toPage;
    isLoading.value = true;
    try {
      final result = await api.list('/admin/v1/identity/list',
          page: page.value, pageSize: pageSize, params: <String, dynamic>{'status': statusFilter});
      list.value = result.rows;
      total.value = result.total;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  /// 换筛选条件必须回到第 1 页：停在第 3 页换上「已通过」很可能整页为空，
  /// 看起来像「没有数据」而不是「页码超了」。
  Future<void> filterByStatus(String status) async {
    statusFilter = status;
    page.value = 1;
    await loadData();
  }

  /// 审核动作（IdentityController::review）：{id: 记录 hashid, action: approve|reject, note: 可选 ≤500}。
  /// **不吞异常**：已审过的记录会 422（CAS 拿 0 行），message 要在表单里原样显示。
  Future<void> review(String id, String action, String note) async {
    await api.put('/admin/v1/identity/review', data: <String, dynamic>{
      'id': id,
      'action': action,
      'note': note,
    });
    await loadData();
    Get.snackbar(
      '${AppTranslations.t('app.success')}',
      '${action == 'approve' ? AppTranslations.t('identity.approved') : AppTranslations.t('identity.rejected')}',
    );
  }
}

class IdentityPage extends GetView<IdentityController> {
  const IdentityPage({super.key});

  /// 字段真值取自 IdentityController::review 的 validator（IdentityController.php:76-80）：
  /// - action：`required|string|in:approve,reject` ⇒ 两值下拉，不用 0/1 翻转控件
  /// - note：`sometimes|nullable|string|max:500`（列宽 game_user_identity.review_note VARCHAR(500)）
  ///   ⇒ 可选；服务端把它拼进驳回通知的正文
  ///
  /// 这里**不新造确认组件**：审核是动作型接口，底座里能承接的是 showCrudForm ——
  /// 标题带对象标识（申请人）+ Save 才提交，等于「看清是谁再动手」；
  /// 驳回是破坏性动作（CAS 已审过就不能再审），Save 之后再走一次 confirmCrudAction，文案带申请人。
  /// 取消那次确认＝什么都不做（表单会连同已填的备注一起关掉，代价可接受：没发生任何写入）。
  static const List<CrudField> _fields = <CrudField>[
    CrudField('action', 'identity.action', type: CrudFieldType.select, required: true, options: <CrudOption>[
      CrudOption('approve', 'identity.approve'),
      CrudOption('reject', 'identity.reject'),
    ]),
    CrudField('note', 'identity.note', type: CrudFieldType.multiline, hint: 'identity.note_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<IdentityController>()) Get.put(IdentityController());
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Text('${AppTranslations.t('identity.title')}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
        const Spacer(),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'pending', label: Text('Pending')),
            ButtonSegment(value: 'approved', label: Text('Approved')),
            ButtonSegment(value: 'rejected', label: Text('Rejected')),
          ],
          selected: {ctrl.statusFilter},
          onSelectionChanged: (v) => ctrl.filterByStatus(v.first),
        ),
      ]),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.list.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/mascot.png', width: 120),
                const SizedBox(height: 12),
                Text("${AppTranslations.t('app.no_data')}"),
              ],
            ),
          );
        }
        return SingleChildScrollView(child: DataTable(columns: const [
          DataColumn(label: Text('User')),
          DataColumn(label: Text('Name')),
          DataColumn(label: Text('ID Type')),
          DataColumn(label: Text('Status')),
          DataColumn(label: Text('Submitted')),
          DataColumn(label: Text('Actions')),
        ], rows: ctrl.list.map((item) {
          final user = item['user'] as Map<String, dynamic>? ?? {};
          final realName = item['real_name']?.toString() ?? '';
          final username = user['username']?.toString() ?? '';
          return DataRow(cells: [
            DataCell(Text(username)),
            DataCell(Text(realName.isEmpty ? '***' : realName)),
            DataCell(Text(item['id_type'] ?? '')),
            DataCell(Chip(label: Text(item['status'] ?? ''), color: WidgetStatePropertyAll(
              item['status'] == 'approved' ? Colors.green.shade50 : item['status'] == 'rejected' ? Colors.red.shade50 : Colors.orange.shade50))),
            DataCell(Text(item['created_at']?.toString().substring(0, 10) ?? '')),
            DataCell(item['status'] == 'pending' ? Row(mainAxisSize: MainAxisSize.min, children: [
              IconButton(
                icon: const Icon(Icons.check, color: Colors.green),
                tooltip: '${AppTranslations.t('identity.approve')}',
                onPressed: () => _openReview(context, ctrl, item, 'approve'),
              ),
              IconButton(
                icon: const Icon(Icons.close, color: Colors.red),
                tooltip: '${AppTranslations.t('identity.reject')}',
                onPressed: () => _openReview(context, ctrl, item, 'reject'),
              ),
            ]) : Text(item['review_note'] ?? '')),
          ]);
        }).toList()));
      })),
      const SizedBox(height: 8),
      Obx(() => CrudPager(
            page: ctrl.page.value,
            total: ctrl.total.value,
            size: IdentityController.pageSize,
            onPage: (p) => ctrl.loadData(toPage: p),
          )),
    ]);
  }

  /// `preset` 决定下拉的初值（approve/reject 两个按钮共用这一个框）。
  Future<void> _openReview(BuildContext context, IdentityController ctrl, dynamic item, String preset) {
    final realName = item['real_name']?.toString() ?? '';
    final username = (item['user'] as Map<String, dynamic>?)?['username']?.toString() ?? '';
    final name = realName.isNotEmpty ? realName : (username.isNotEmpty ? username : item['id'].toString());
    return showCrudForm(
      context,
      title: crudText('identity.review_title', {'name': name}),
      fields: _fields,
      initial: <String, dynamic>{'action': preset},
      onSubmit: (data) {
        final action = data['action'].toString();
        final note = data['note']?.toString() ?? '';
        if (action != 'reject') {
          return ctrl.review(item['id'].toString(), action, note);
        }
        return confirmCrudAction(
          context,
          title: '${crudText('app.confirm')} ${crudText('identity.reject')}',
          message: crudText('identity.reject_confirm_target', {'name': name}),
          confirmLabel: crudText('identity.reject'),
          onConfirm: () => ctrl.review(item['id'].toString(), action, note),
        );
      },
    );
  }
}
