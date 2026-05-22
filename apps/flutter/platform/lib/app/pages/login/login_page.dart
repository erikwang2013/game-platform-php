// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:dio/dio.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _dio = Dio(BaseOptions(baseUrl: ApiService.baseUrl, headers: {'API-Version': 'v1'}));
  bool _loading = false;
  bool _isRegister = false;
  String? _error;

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;

    if (username.isEmpty || password.isEmpty) {
      setState(() => _error = '请输入用户名和密码');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final endpoint = _isRegister ? '/api/auth/register' : '/api/auth/login';
      final resp = await _dio.post(endpoint, data: {
        'username': username,
        'password': password,
      });

      if (resp.data['code'] == 0) {
        final data = resp.data['data'];
        await AuthService.saveLogin(
          token: data['access_token'] as String,
          refreshToken: data['refresh_token'] as String,
          username: data['user']['username'] as String,
        );
        if (mounted) Get.offAllNamed('/games');
      } else {
        setState(() => _error = resp.data['message'] ?? '操作失败');
      }
    } on DioException catch (e) {
      setState(() => _error = e.response?.data?['message'] ?? '网络错误，请检查连接');
    } catch (e) {
      setState(() => _error = '网络错误，请检查连接');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: colorScheme.surfaceContainerLowest,
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Logo area
                Icon(Icons.sports_esports, size: 64, color: colorScheme.primary),
                const SizedBox(height: 12),
                Text(
                  '全球游戏聚合平台',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: colorScheme.primary),
                ),
                const SizedBox(height: 8),
                Text(
                  _isRegister ? '创建新账号' : '欢迎回来',
                  style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 32),

                // Username field
                TextField(
                  controller: _usernameCtrl,
                  decoration: const InputDecoration(
                    labelText: '用户名',
                    prefixIcon: Icon(Icons.person_outline),
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),

                // Password field
                TextField(
                  controller: _passwordCtrl,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: '密码',
                    prefixIcon: Icon(Icons.lock_outline),
                    border: OutlineInputBorder(),
                  ),
                  onSubmitted: (_) => _submit(),
                ),
                const SizedBox(height: 24),

                // Error message
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
                  const SizedBox(height: 16),
                ],

                // Submit button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton(
                    onPressed: _loading ? null : _submit,
                    child: _loading
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Text(_isRegister ? '注 册' : '登 录', style: const TextStyle(fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 16),

                // Toggle login/register
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _isRegister ? '已有账号？' : '没有账号？',
                      style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant),
                    ),
                    TextButton(
                      onPressed: () => setState(() {
                        _isRegister = !_isRegister;
                        _error = null;
                      }),
                      child: Text(
                        _isRegister ? '去登录' : '去注册',
                        style: const TextStyle(fontSize: 14),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                Text(
                  'Copyright (c) 2026 erik — https://erik.xyz',
                  style: TextStyle(fontSize: 11, color: colorScheme.onSurface.withValues(alpha: 0.3)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
