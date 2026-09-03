/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../services/api_service.dart';

class UserController extends GetxController {
  final api = ApiService();

  final users = <dynamic>[].obs;
  final isLoading = false.obs;
  final total = 0.obs;
  final page = 1.obs;
  final limit = 15.obs;
  final keyword = ''.obs;
  final statusFilter = Rx<int?>(null);
  final keywordCtrl = TextEditingController();
  final selectedIds = <String>{}.obs;

  @override
  void onInit() {
    super.onInit();
    _restoreFilters();
    loadUsers();
  }

  @override
  void onClose() {
    keywordCtrl.dispose();
    super.onClose();
  }

  /// 筛选条件持久化：shared_preferences 恢复上次筛选
  Future<void> _restoreFilters() async {
    final prefs = await SharedPreferences.getInstance();
    keyword.value = prefs.getString('user_filter.keyword') ?? '';
    statusFilter.value = prefs.getInt('user_filter.status');
    keywordCtrl.text = keyword.value;
  }

  Future<void> loadUsers({bool reset = false}) async {
    if (reset) page.value = 1;
    isLoading.value = true;
    try {
      final params = <String, dynamic>{
        'page': page.value,
        'limit': limit.value,
      };
      if (keyword.value.isNotEmpty) params['keyword'] = keyword.value;
      if (statusFilter.value != null) params['status'] = statusFilter.value;

      final resp = await api.get('/admin/v1/user', params: params);
      users.value = resp['data']['list'] as List<dynamic>;
      total.value = resp['data']['total'] as int;
    } catch (e) {
      Get.snackbar('错误', '加载用户列表失败: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> search(String kw) async {
    keyword.value = kw;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user_filter.keyword', kw);
    await loadUsers(reset: true);
  }

  Future<void> filterByStatus(int? status) async {
    statusFilter.value = status;
    final prefs = await SharedPreferences.getInstance();
    if (status == null) {
      await prefs.remove('user_filter.status');
    } else {
      await prefs.setInt('user_filter.status', status);
    }
    await loadUsers(reset: true);
  }

  Future<void> nextPage() async {
    if (page.value * limit.value < total.value) {
      page.value++;
      await loadUsers();
    }
  }

  Future<void> prevPage() async {
    if (page.value > 1) {
      page.value--;
      await loadUsers();
    }
  }

  Future<bool> deleteUser(String id, String password) async {
    try {
      await api.delete('/admin/v1/user/$id', data: {'password': password});
      await loadUsers();
      return true;
    } catch (e) {
      Get.snackbar('错误', '删除失败: $e');
      return false;
    }
  }

  Future<bool> batchDelete(String password) async {
    if (selectedIds.isEmpty) {
      Get.snackbar('提示', '请先选择用户');
      return false;
    }
    try {
      await api.post('/admin/v1/user/batch/destroy', data: {
        'ids': selectedIds.toList(),
        'password': password,
      });
      selectedIds.clear();
      await loadUsers();
      Get.snackbar('成功', '批量删除完成');
      return true;
    } catch (e) {
      Get.snackbar('错误', '批量删除失败: $e');
      return false;
    }
  }

  Future<bool> batchSetStatus(int status) async {
    if (selectedIds.isEmpty) {
      Get.snackbar('提示', '请先选择用户');
      return false;
    }
    try {
      await api.post('/admin/v1/user/batch/status', data: {
        'ids': selectedIds.toList(),
        'status': status,
      });
      selectedIds.clear();
      await loadUsers();
      Get.snackbar('成功', status == 1 ? '批量启用完成' : '批量禁用完成');
      return true;
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
      return false;
    }
  }

  void toggleSelect(String id) {
    if (selectedIds.contains(id)) {
      selectedIds.remove(id);
    } else {
      selectedIds.add(id);
    }
  }

  void toggleSelectAll() {
    if (selectedIds.length == users.length) {
      selectedIds.clear();
    } else {
      selectedIds.addAll(users.map((u) => u['id'].toString()));
    }
  }
}
