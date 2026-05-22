// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
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
  final _nicknameCtrl = TextEditingController();
  final _avatarCtrl = TextEditingController();
  final _languageCtrl = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;
  String? _successMsg;
  Map<String, dynamic>? _profile;

  @override
  void initState() {
    super.initState();
    _fetchProfile();
  }

  @override
  void dispose() {
    _nicknameCtrl.dispose();
    _avatarCtrl.dispose();
    _languageCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchProfile() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/user/profile');
      final data = resp['data'];
      if (mounted) {
        setState(() {
          _profile = data;
          _nicknameCtrl.text = data?['nickname'] ?? '';
          _avatarCtrl.text = data?['avatar'] ?? '';
          _languageCtrl.text = data?['language'] ?? '';
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = '加载失败，请重试';
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
      _successMsg = null;
    });

    try {
      await _api.put('/api/user/profile', data: {
        'nickname': _nicknameCtrl.text.trim(),
        'avatar': _avatarCtrl.text.trim(),
        'language': _languageCtrl.text.trim(),
      });
      setState(() {
        _saving = false;
        _successMsg = '保存成功';
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _saving = false;
      });
    } catch (e) {
      setState(() {
        _error = '网络错误，请重试';
        _saving = false;
      });
    }
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('确认退出'),
        content: const Text('确定要退出登录吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('取消')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('确定退出', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirm == true) {
      await AuthService.clearToken();
      Get.offAllNamed('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('个人中心'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
      ),
      body: Container(
        color: colorScheme.surfaceContainerLowest,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 800),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Profile info card
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  CircleAvatar(
                                    radius: 36,
                                    backgroundColor: colorScheme.primaryContainer,
                                    child: Icon(Icons.person, size: 36, color: colorScheme.onPrimaryContainer),
                                  ),
                                  const SizedBox(width: 16),
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _profile?['username'] ?? '-',
                                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        '账号信息',
                                        style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                              const Divider(height: 32),
                              _buildInfoRow('用户名', _profile?['username'] ?? '-'),
                              _buildInfoRow('昵称', _profile?['nickname'] ?? '-'),
                              _buildInfoRow('国家', _profile?['country'] ?? '-'),
                              _buildInfoRow('语言', _profile?['language'] ?? '-'),
                              _buildInfoRow('注册日期', _profile?['created_at'] ?? '-'),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Edit form
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('编辑资料', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 16),

                              TextField(
                                controller: _nicknameCtrl,
                                decoration: const InputDecoration(
                                  labelText: '昵称',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                              const SizedBox(height: 16),

                              TextField(
                                controller: _avatarCtrl,
                                decoration: const InputDecoration(
                                  labelText: '头像 URL',
                                  hintText: '输入头像图片链接',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                              const SizedBox(height: 16),

                              TextField(
                                controller: _languageCtrl,
                                decoration: const InputDecoration(
                                  labelText: '语言',
                                  hintText: '如 zh-CN, en-US',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                              const SizedBox(height: 20),

                              // Success / Error messages
                              if (_successMsg != null) ...[
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Colors.green.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(children: [
                                    const Icon(Icons.check_circle, color: Colors.green, size: 18),
                                    const SizedBox(width: 8),
                                    Text(_successMsg!, style: const TextStyle(color: Colors.green, fontSize: 13)),
                                  ]),
                                ),
                                const SizedBox(height: 12),
                              ],
                              if (_error != null) ...[
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Colors.red.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(children: [
                                    const Icon(Icons.error_outline, color: Colors.red, size: 18),
                                    const SizedBox(width: 8),
                                    Expanded(child: Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13))),
                                  ]),
                                ),
                                const SizedBox(height: 12),
                              ],

                              // Save button
                              SizedBox(
                                height: 44,
                                child: FilledButton(
                                  onPressed: _saving ? null : _save,
                                  child: _saving
                                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                      : const Text('保存', style: TextStyle(fontSize: 16)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Logout
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              const Icon(Icons.logout, color: Colors.red, size: 20),
                              const SizedBox(width: 12),
                              const Text('退出登录', style: TextStyle(fontSize: 15, color: Colors.red)),
                              const Spacer(),
                              OutlinedButton(
                                onPressed: _logout,
                                style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                                child: const Text('退出登录'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          SizedBox(
            width: 80,
            child: Text(label, style: TextStyle(fontSize: 13, color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 14))),
        ],
      ),
    );
  }
}
