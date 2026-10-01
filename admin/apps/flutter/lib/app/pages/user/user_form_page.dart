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
      // 改他人密码是敏感操作，要**两个不同名**的字段：`password` 是新密码，
      // `admin_password` 是**当前操作者自己的**登录密码（UserController::update →
      // BaseController::confirmPassword 二次确认）。只发前者必被 422 挡下，且服务端只说
      // 「This sensitive operation requires password confirmation」，不告诉你是缺了哪个。
      final adminPassword = await _promptAdminPassword();
      if (adminPassword == null) return; // 取消：不发请求，也不进 loading 态
      data['password'] = _passwordCtrl.text;
      data['admin_password'] = adminPassword;
    }

    setState(() => _isLoading = true);
    try {
      final api = ApiService();
      if (isEdit) {
        await api.put('/admin/v1/user/${widget.userData!['id']}', data: data);
      } else {
        await api.post('/admin/v1/user', data: data);
      }
      Get.snackbar('成功', isEdit ? 'userUpdateSuccess' : 'userCreateSuccess');
      Get.back(result: true);
    } catch (e) {
      Get.snackbar('错误', '操作失败: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  /// 二次确认框，收的必须是**操作者自己的**密码（与被改的那个账号无关）。
  /// 返回 null = 用户取消。
  ///
  /// 不建 controller：TextField 无 controller 时自管输入态，值经 onChanged 回传 —— 与
  /// `widgets/crud.dart` 的 confirmCrudAction 同法（弹框关闭动画期间 TextField 仍会重建，
  /// 在那里持有 controller 会在 dispose 后被用到）。
  Future<String?> _promptAdminPassword() async {
    var password = '';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('${AppTranslations.t('app.confirm')}'),
        content: TextField(
          obscureText: true,
          autofocus: true,
          onChanged: (v) => password = v,
          decoration: InputDecoration(labelText: '${AppTranslations.t('user.password_confirm_hint')}'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text('${AppTranslations.t('app.cancel')}')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: Text('${AppTranslations.t('app.confirm')}')),
        ],
      ),
    );
    return ok == true ? password : null;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(isEdit ? '${AppTranslations.t('user.edit')}' : '${AppTranslations.t('user.create')}')),
      body: Center(
        child: SizedBox(
          width: 500,
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                TextFormField(controller: _usernameCtrl, enabled: !isEdit, decoration: InputDecoration(labelText: "${AppTranslations.t('user.username')}"), validator: (v) => (v == null || v.isEmpty) ? '请输入用户名' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _passwordCtrl, obscureText: true, decoration: InputDecoration(labelText: isEdit ? '${AppTranslations.t('user.new_password_hint')}' : '${AppTranslations.t('login.password')}'), validator: (v) => !isEdit && (v == null || v.isEmpty) ? 'passwordRequired' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _realNameCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.real_name')}'), validator: (v) => (v == null || v.isEmpty) ? '请输入真实姓名' : null),
                const SizedBox(height: 16),
                TextFormField(controller: _phoneCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.phone')}')),
                const SizedBox(height: 16),
                TextFormField(controller: _emailCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.email')}')),
                const SizedBox(height: 16),
                DropdownButtonFormField<int>(initialValue: _status, decoration: InputDecoration(labelText: '${AppTranslations.t('user.status')}'), items: [
                  DropdownMenuItem(value: 1, child: Text("${AppTranslations.t('app.enabled')}")),
                  DropdownMenuItem(value: 0, child: Text("${AppTranslations.t('app.disabled')}")),
                ], onChanged: (v) => setState(() => _status = v ?? 1)),
                const SizedBox(height: 24),
                ElevatedButton(onPressed: _isLoading ? null : _submit, child: Text(_isLoading ? 'Submitting...' : '${AppTranslations.t('app.save')}')),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
