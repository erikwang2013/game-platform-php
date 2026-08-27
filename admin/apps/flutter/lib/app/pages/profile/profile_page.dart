/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import '../../i18n/translations.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final _api = ApiService();
  final _realNameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    setState(() => _loading = true);
    try {
      // Leave real_name field empty for user to fill; show username as label
      // User can update real_name via this form
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _updateProfile() async {
    try {
      await _api.put('/admin/profile', data: {
        'real_name': _realNameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'email': _emailCtrl.text.trim(),
      });
      Get.snackbar('成功', 'profileUpdateSuccess');
    } catch (e) {
      Get.snackbar('错误', '更新失败: $e');
    }
  }

  Future<void> _changePassword() async {
    final oldPwdCtrl = TextEditingController();
    final newPwdCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('${AppTranslations.t('profile.update_password')}'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: oldPwdCtrl, obscureText: true, decoration: InputDecoration(labelText: '${AppTranslations.t('profile.old_password')}')),
          TextField(controller: newPwdCtrl, obscureText: true, decoration: InputDecoration(labelText: '${AppTranslations.t('profile.new_password')}')),
          TextField(controller: confirmCtrl, obscureText: true, decoration: InputDecoration(labelText: '${AppTranslations.t('profile.confirm_password')}')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: Text('${AppTranslations.t('app.confirm')}')),
        ],
      ),
    );

    if (ok != true) return;
    if (newPwdCtrl.text != confirmCtrl.text) {
      Get.snackbar('错误', 'passwordMismatch');
      return;
    }

    try {
      await _api.put('/admin/profile/password', data: {
        'old_password': oldPwdCtrl.text,
        'new_password': newPwdCtrl.text,
      });
      Get.snackbar('成功', 'passwordChangedSuccess');
    } catch (e) {
      Get.snackbar('错误', '修改失败: $e');
    }
  }

  Future<void> _logout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('${AppTranslations.t('profile.logout')}'),
        content: Text('${AppTranslations.t('profile.logout_confirm')}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text("${AppTranslations.t('app.cancel')}")),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white), child: Text('${AppTranslations.t('app.logout')}')),
        ],
      ),
    );
    if (ok != true) return;
    try { await _api.post('/admin/profile/logout'); } catch (_) {}
    await AuthService.clearToken();
    Get.offAllNamed('/login');
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    return Center(child: SizedBox(width: 500, child: ListView(padding: const EdgeInsets.all(24), children: [
      Text("${AppTranslations.t('profile.title')}", style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
      const SizedBox(height: 24),
      TextField(controller: _realNameCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.real_name')}')),
      const SizedBox(height: 12),
      TextField(controller: _phoneCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.phone')}')),
      const SizedBox(height: 12),
      TextField(controller: _emailCtrl, decoration: InputDecoration(labelText: '${AppTranslations.t('user.email')}')),
      const SizedBox(height: 24),
      Row(children: [
        ElevatedButton.icon(onPressed: _updateProfile, icon: const Icon(Icons.save), label: Text("${AppTranslations.t('app.save')}")),
      ]),
      const SizedBox(height: 32),
      const Divider(),
      ListTile(leading: const Icon(Icons.lock), title: Text('${AppTranslations.t('profile.update_password')}'), trailing: const Icon(Icons.chevron_right), onTap: _changePassword),
      ListTile(leading: const Icon(Icons.logout, color: Colors.red), title: Text('${AppTranslations.t('profile.logout')}', style: TextStyle(color: Colors.red)), onTap: _logout),
    ])));
  }
}
