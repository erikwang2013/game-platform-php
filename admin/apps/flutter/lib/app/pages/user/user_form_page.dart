/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class UserFormPage extends StatefulWidget {
  final Map<String, dynamic>? userData;
  const UserFormPage({super.key, this.userData});

  @override
  State<UserFormPage> createState() => _UserFormPageState();
}

class _UserFormPageState extends State<UserFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _realNameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  int _status = 1;
  bool _isLoading = false;

  bool get isEdit => widget.userData != null;

  @override
  void initState() {
    super.initState();
    if (isEdit) {
      _usernameCtrl.text = widget.userData!['username'] ?? '';
      _realNameCtrl.text = widget.userData!['real_name'] ?? '';
      _phoneCtrl.text = widget.userData!['phone'] ?? '';
      _emailCtrl.text = widget.userData!['email'] ?? '';
      _status = widget.userData!['status'] ?? 1;
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _isLoading = true);

    final data = {
      'real_name': _realNameCtrl.text.trim(),
      'status': _status,
      'phone': _phoneCtrl.text.trim(),
      'email': _emailCtrl.text.trim(),
    };
    if (!isEdit) {
      data['username'] = _usernameCtrl.text.trim();
      data['password'] = _passwordCtrl.text;
    } else if (_passwordCtrl.text.isNotEmpty) {
      data['password'] = _passwordCtrl.text;
    }

    try {
      final api = ApiService();
      if (isEdit) {
        await api.put('/admin/user/${widget.userData!['id']}', data: data);
      } else {
        await api.post('/admin/user', data: data);
      }
      Get.snackbar('成功', isEdit ? '用户更新成功' : '用户创建成功');
      Get.back(result: true);
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(isEdit ? '编辑用户' : '新增用户')),
      body: Center(
        child: SizedBox(
          width: 500,
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                TextFormField(controller: _usernameCtrl, enabled: !isEdit, decoration: const InputDecoration(labelText: "${AppTranslations.t('user.username')}"), validator: (v) => (v == null || v.isEmpty) ? '请输入用户名' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _passwordCtrl, obscureText: true, decoration: InputDecoration(labelText: isEdit ? '新密码（留空不修改）' : '密码'), validator: (v) => !isEdit && (v == null || v.isEmpty) ? '请输入密码' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _realNameCtrl, decoration: const InputDecoration(labelText: '真实姓名'), validator: (v) => (v == null || v.isEmpty) ? '请输入真实姓名' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _phoneCtrl, decoration: const InputDecoration(labelText: '手机号')),
                const SizedBox(height: 16),
                TextFormField(controller: _emailCtrl, decoration: const InputDecoration(labelText: '邮箱')),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(value: _status, decoration: const InputDecoration(labelText: '状态'), items: const [
                  DropdownMenuItem(value: 1, child: Text("${AppTranslations.t('app.enabled')}")),
                  DropdownMenuItem(value: 0, child: Text("${AppTranslations.t('app.disabled')}")),
                ], onChanged: (v) => setState(() => _status = v ?? 1)),
                const SizedBox(height: 24),
                ElevatedButton(onPressed: _isLoading ? null : _submit, child: Text(_isLoading ? '提交中...' : '提交')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
