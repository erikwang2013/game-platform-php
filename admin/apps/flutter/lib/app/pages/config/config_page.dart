/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../widgets/crud.dart';

class ConfigController extends GetxController {
  final api = ApiService();
  final configs = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;

  @override
  void onInit() { super.onInit(); loadConfigs(); }

  Future<void> loadConfigs() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/config', params: {'page': page.value, 'limit': limit.value});
      configs.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally { isLoading.value = false; }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单里显示服务端 message（widgets/crud.dart）。

  Future<void> create(Map<String, dynamic> data) async {
    await api.post('/admin/v1/config', data: data);
    await loadConfigs();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 路径参数名是 `{id}`（不是 {hashid}），但值仍是列表里那个 hashid：
  /// 列表走 encodeIds 编的是 `id` 列，destroy 那头也是 decodeId 解的
  Future<void> updateConfig(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/config/$hashid', data: data);
    await loadConfigs();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 删除需管理员密码二次确认（ConfigController::destroy 的 confirmPassword），
  /// 密码由确认框里的输入框提供、随请求体发过去
  Future<void> remove(String hashid, String password) async {
    await api.delete('/admin/v1/config/$hashid', data: <String, dynamic>{'password': password});
    await loadConfigs();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}

class ConfigPage extends GetView<ConfigController> {
  const ConfigPage({super.key});

  /// 字段真值取自 ConfigController::store/update 的 validator + game_platform_config 列定义：
  /// - group/key：只有 store 收（`required|string|max:100`，另有同组同键查重），update 的规则里没有
  ///   ⇒ 两列编辑态置灰且不提交
  /// - value：`required|string`（create/update 同款），多行
  /// - type：不做枚举收口（PlatformConfig::get() 对未知 type 走 default 分支返回字符串），
  ///   列宽 VARCHAR(20) ⇒ 文本控件 + 提示
  static const List<CrudField> _fields = <CrudField>[
    CrudField('group', 'config.group', required: true, editableOnEdit: false),
    CrudField('key', 'config.key', required: true, editableOnEdit: false),
    CrudField('value', 'config.value', type: CrudFieldType.multiline, required: true),
    CrudField('type', 'config.type', hint: 'config.type_hint'),
    CrudField('description', 'config.description'),
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<ConfigController>()) {
      Get.put(ConfigController(), permanent: false);
    }
    final ctrl = controller;

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CrudHeader(
        title: "${AppTranslations.t('config.title')}",
        onCreate: () => _openForm(context, ctrl),
      ),
      const SizedBox(height: 12),
      Expanded(child: Obx(() {
        if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
        if (ctrl.configs.isEmpty) {
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
          itemCount: ctrl.configs.length,
          itemBuilder: (_, i) {
            final c = ctrl.configs[i];
            // 列表里的 id 是 hashid：{id} 路径用的就是它
            final id = c['id']?.toString() ?? '';
            final label = '${c['group']}.${c['key']}';
            return Card(child: ListTile(
              title: Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text(
                '${c['type'] ?? 'string'}  |  ${c['description'] ?? ''}\n${c['value'] ?? ''}',
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
              isThreeLine: true,
              trailing: CrudRowActions(
                onEdit: () => _openForm(context, ctrl, item: c),
                // 密码经确认框回传（服务端空密码直接 422），失败时服务端 message 弹在 snackbar 里
                onDelete: () async {
                  var password = '';
                  await confirmCrudDelete(
                    context,
                    what: label,
                    onPassword: (value) => password = value,
                    onConfirm: () => ctrl.remove(id, password),
                  );
                },
              ),
            ));
          },
        );
      })),
    ]);
  }

  Future<void> _openForm(BuildContext context, ConfigController ctrl, {dynamic item}) {
    final initial = item == null ? null : Map<String, dynamic>.from(item as Map);
    return showCrudForm(
      context,
      title: item == null ? '${AppTranslations.t('config.create')}' : '${AppTranslations.t('config.edit')}',
      fields: _fields,
      initial: initial,
      onSubmit: (data) => item == null ? ctrl.create(data) : ctrl.updateConfig(item['id'].toString(), data),
    );
  }
}
