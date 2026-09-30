/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';

class RoleController extends GetxController {
  final api = ApiService();
  final roles = <dynamic>[].obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadRoles();
  }

  /// 列表端点是 `/admin/v1/role`（Route::resource，**不是** `/role/list`）。
  // ponytail: 角色是运维手工维护的个位数实体，取大 limit 代替分页控件；真的超过 100 条再补分页。
  Future<void> loadRoles() async {
    isLoading.value = true;
    try {
      final resp = await api.get('/admin/v1/role', params: {'limit': 100});
      roles.value = resp['data']['list'] as List<dynamic>;
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}: $e');
    } finally {
      isLoading.value = false;
    }
  }

  // 以下写操作**不吞异常**：异常要冒到通用表单/确认框里显示服务端 message（widgets/crud.dart）。

  Future<void> createRole(Map<String, dynamic> data) async {
    await api.post('/admin/v1/role', data: data);
    await loadRoles();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 改名/描述/启停都走这里（RoleController::update，局部更新；没有独立 toggle 端点）。
  Future<void> updateRole(String hashid, Map<String, dynamic> data) async {
    await api.put('/admin/v1/role/$hashid', data: data);
    await loadRoles();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.saved')}');
  }

  /// 角色删除要管理员密码（RoleController::destroy 的 confirmPassword 会先 422 空密码）。
  Future<void> destroyRole(String hashid, String password) async {
    await api.delete('/admin/v1/role/$hashid', data: <String, dynamic>{'password': password});
    await loadRoles();
    Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('app.deleted')}');
  }
}
