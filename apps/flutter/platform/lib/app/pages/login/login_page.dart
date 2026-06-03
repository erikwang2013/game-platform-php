// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import '../../i18n/locale_controller.dart';
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
      setState(() => _error = "${AppTranslations.t('login.enter_credentials')}");
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
        setState(() => _error = resp.data['message'] ?? "${AppTranslations.t('login.operation_failed')}");
      }
    } on DioException catch (e) {
      setState(() => _error = e.response?.data?['message'] ?? "${AppTranslations.t('app.network_error')}");
    } catch (e) {
      setState(() => _error = "${AppTranslations.t('app.network_error')}");
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _oauthLogin(String provider) async {
    try {
      final api = ApiService();
      // Step 1: Get redirect URL
      final redirectResp = await api.get('/auth/oauth/$provider');
      final redirectUrl = redirectResp['data']['redirect_url'] as String?;
      if (redirectUrl == null) {
        Get.snackbar('Error', 'OAuth configuration not available');
        return;
      }
      // Step 2: For web MVP, simulate the callback
      // In production, this would open a popup window and handle the redirect
      // For now, simulate with a test code
      final callbackResp = await api.post('/auth/oauth/$provider/callback', data: {
        'code': 'test_oauth_code_${DateTime.now().millisecondsSinceEpoch}',
        'state': 'test_state',
      });
      final data = callbackResp['data'];
      await AuthService.saveLogin(
        token: data['access_token'],
        refreshToken: data['refresh_token'],
        username: data['user']?['username'] ?? '',
      );
      Get.offAllNamed('/games');
      if (data['is_new'] == true) {
        Get.snackbar('Welcome', 'Account created successfully!');
      }
    } catch (e) {
      Get.snackbar('Error', 'OAuth login failed: $e');
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
                  "${AppTranslations.t('login.title')}",
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: colorScheme.primary),
                ),
                const SizedBox(height: 8),
                Text(
                  _isRegister ? "${AppTranslations.t('login.create_account')}" : "${AppTranslations.t('login.welcome')}",
                  style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 32),

                // Username field
                TextField(
                  controller: _usernameCtrl,
                  decoration: InputDecoration(
                    labelText: "${AppTranslations.t('profile.username')}",
                    prefixIcon: Icon(Icons.person_outline),
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),

                // Password field
                TextField(
                  controller: _passwordCtrl,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: "${AppTranslations.t('login.password')}",
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
                        : Text(_isRegister ? '${AppTranslations.t('login.register')}' : '${AppTranslations.t('login.login')}', style: const TextStyle(fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 16),

                // Toggle login/register
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _isRegister ? "${AppTranslations.t('login.have_account')}" : "${AppTranslations.t('login.no_account')}",
                      style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant),
                    ),
                    TextButton(
                      onPressed: () => setState(() {
                        _isRegister = !_isRegister;
                        _error = null;
                      }),
                      child: Text(
                        _isRegister ? "${AppTranslations.t('login.go_login')}" : "${AppTranslations.t('login.go_register')}",
                        style: const TextStyle(fontSize: 14),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                const Row(children: [
                  Expanded(child: Divider()),
                  Padding(padding: EdgeInsets.symmetric(horizontal: 16), child: Text('or')),
                  Expanded(child: Divider()),
                ]),
                const SizedBox(height: 16),
                // OAuth buttons
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.g_mobiledata, size: 24),
                    label: const Text('Continue with Google'),
                    onPressed: () => _oauthLogin('google'),
                    style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(12)),
                  ),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.facebook, size: 24),
                    label: const Text('Continue with Facebook'),
                    onPressed: () => _oauthLogin('facebook'),
                    style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(12)),
                  ),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.apple, size: 24),
                    label: const Text('Continue with Apple'),
                    onPressed: () => _oauthLogin('apple'),
                    style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(12)),
                  ),
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
