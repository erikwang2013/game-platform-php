// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class VipController extends GetxController {
  final api = ApiService();
  final items = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() { super.onInit(); load(); }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/vip/level/list');
      items.value = resp['data']['list'] as List<dynamic>;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally { isLoading.value = false; }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/vip/level/create', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> updateLevel(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/vip/level/$hashid', data: data);
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  Future<void> remove(String hashid) async {
    await api.delete('/admin/v1/vip/level/$hashid');
    await load();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class VipPage extends GetView<VipController> {
  const VipPage({super.key});

  /// 字段真值取自 VipLevelController::create/update 的 validator + game_vip_level 列定义：
  /// - level：只有 create 收（`required|integer|min:0`，另有同等级查重），update 的规则里没有 ⇒ 编辑态置灰且不提交
  /// - required_exp：INT UNSIGNED ⇒ min:0
  /// - benefits：JSON **对象**，键白名单 exchange_discount / withdraw_fee_discount / rate_bonus，
  ///   值必须落在 [0,1]（VipLevelController::validateBenefits 逐键校验；键名打错时 VipService 一律按 0 处理，
  ///   界面上配了折扣而实际一分没折）。JSON 合法性交给服务端判，422 的 message 显示在框内
  static const List<CrudField> _fields = <CrudField>[
    CrudField('level', 'vip.level', type: CrudFieldType.number, required: true, editableOnEdit: false),
    CrudField('name', 'vip.name', required: true),
    CrudField('required_exp', 'vip.required_exp', type: CrudFieldType.number, required: true),
    CrudField('benefits', 'vip.benefits',
        type: CrudFieldType.multiline, required: true, maxLines: 5, hint: 'vip.benefits_hint'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<VipController>()) Get.put(VipController(), permanent: false);
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('vip.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.items.isEmpty) {
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
        return ListView.builder(
          itemCount: ctrl.items.length,
          itemBuilder: (_, i) {
            final item = ctrl.items[i];
            // 列表里的 id 是 hashid：{hashid} 路径用它
            final id = item['id']?.toString() ?? '';
            final name = item['name']?.toString() ?? '';
            final benefits = item['benefits'] ?? '';
            return Card(child: ListTile(
              leading: CircleAvatar(child: Text('${item['level'] ?? ''}')),
              title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text('${AppTranslations.t('vip.required_exp')}: ${item['required_exp']}\n$benefits'),
              isThreeLine: true,
              trailing: CrudRowActions(
                // vip_level 没有 status 列，也没有 toggle 端点：只有编辑与删除
                onEdit: () => _openForm(context, ctrl, item: item),
                onDelete: () => confirmCrudDelete(context, what: name, onConfirm: () => ctrl.remove(id)),
              ),
            ));
          },
        );
      })),
    ]);
  }

  Future<void> _openForm(BuildContext context, VipController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('vip.create')}' : '${AppTranslations.t('vip.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateLevel(item['id'].toString(), data),
    );
  }
}
