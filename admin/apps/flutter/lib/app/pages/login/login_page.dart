// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/captcha_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  static final _headers = {'API-Version': 'v1'};
  final _dio = Dio(BaseOptions(baseUrl: ApiService.baseUrl, headers: _headers));
  final _captcha = CaptchaService(Dio(BaseOptions(baseUrl: ApiService.baseUrl, headers: _headers)));

  bool _loading = false;
  String? _error;

  // Captcha state
  CaptchaData? _captchaData;
  Uint8List? _captchaImage;
  final List<Offset> _clicks = [];
  final List<String> _clickLabels = [];

  @override
  void initState() {
    super.initState();
    _loadCaptcha();
  }

  Future<void> _loadCaptcha() async {
    try {
      _captchaData = await _captcha.generate();
      setState(() {
        _captchaImage = base64Decode(_captchaData!.imageBase64.replaceFirst(RegExp(r'^data:image/\w+;base64,'), ''));
        _clicks.clear();
        _clickLabels.clear();
      });
    } catch (_) {
      setState(() => _error = 'captchaLoadFailed');
    }
  }

  void _onCaptchaTap(TapUpDetails detail, BoxConstraints constraints) {
    if (_captchaData == null || _clicks.length >= _captchaData!.targets.length) return;

    final dx = detail.localPosition.dx;
    final dy = detail.localPosition.dy;

    // Convert from widget coordinates to image coordinates
    final scaleX = 400.0 / constraints.maxWidth;
    final scaleY = 250.0 / constraints.maxHeight;
    final imgX = (dx * scaleX).round();
    final imgY = (dy * scaleY).round();

    final target = _captchaData!.targets[_clicks.length];
    setState(() {
      _clicks.add(Offset(imgX.toDouble(), imgY.toDouble()));
      _clickLabels.add('${target.order}');
      _error = null;
    });
  }

  Future<void> _login() async {
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;

    if (username.isEmpty || password.isEmpty) {
      setState(() => _error = 'enterCredentials');
      return;
    }
    if (_captchaData == null) {
      setState(() => _error = 'loadCaptcha');
      return;
    }
    if (_clicks.length < _captchaData!.targets.length) {
      setState(() => _error = '请按顺序点击图中文字『${_captchaData!.targets[_clicks.length].text}』');
      return;
    }

    setState(() => _loading = true);

    try {
      final resp = await _dio.post('/api/auth/login', data: {
        'username': username,
        'password': password,
        'captcha_key': _captchaData!.key,
        'clicks': _clicks.map((c) => {'x': c.dx.round(), 'y': c.dy.round()}).toList(),
      });

      if (resp.data['code'] == 0) {
        final data = resp.data['data'];
        await AuthService.saveLogin(
          token: data['access_token'] as String,
          refreshToken: data['refresh_token'] as String,
          username: data['user']['username'] as String,
        );
        if (mounted) Navigator.of(context).pushReplacementNamed('/dashboard');
      } else {
        setState(() => _error = resp.data['message'] ?? 'loginFailed');
        _loadCaptcha();
      }
    } catch (e) {
      setState(() => _error = 'networkErrorCheck');
      _loadCaptcha();
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 400),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.admin_panel_settings, size: 64, color: Color(0xFF1677FF)),
                const SizedBox(height: 12),
                Text("${AppTranslations.t('app.title')}", style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Color(0xFF1677FF))),
                const SizedBox(height: 32),

                // Username
                TextField(
                  controller: _usernameCtrl,
                  decoration: InputDecoration(
                    labelText: "${AppTranslations.t('user.username')}",
                    prefixIcon: Icon(Icons.person_outline),
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),

                // Password
                TextField(
                  controller: _passwordCtrl,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: '${AppTranslations.t('login.password')}',
                    prefixIcon: Icon(Icons.lock_outline),
                    border: OutlineInputBorder(),
                  ),
                  onSubmitted: (_) => _login(),
                ),
                const SizedBox(height: 20),

                // Click Captcha
                if (_captchaImage != null && _captchaData != null) ...[
                  Text('请按顺序点击图中文字: ${_captchaData!.targets.map((t) => '"${t.text}"').join(' → ')}',
                      style: const TextStyle(fontSize: 13, color: Colors.black87)),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: GestureDetector(
                      onTapUp: (d) => _onCaptchaTap(d, BoxConstraints.tightFor(width: 400, height: 250)),
                      child: LayoutBuilder(
                        builder: (context, constraints) {
                          return Stack(
                            children: [
                              Image.memory(_captchaImage!, width: 400, height: 250, fit: BoxFit.contain),
                              // Click markers
                              ..._clicks.asMap().entries.map((entry) {
                                final idx = entry.key;
                                final c = entry.value;
                                final widgetX = (c.dx / 400) * constraints.maxWidth;
                                final widgetY = (c.dy / 250) * constraints.maxHeight;
                                return Positioned(
                                  left: widgetX - 14,
                                  top: widgetY - 14,
                                  child: Container(
                                    width: 28,
                                    height: 28,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF1677FF).withValues(alpha: 0.8),
                                      shape: BoxShape.circle,
                                    ),
                                    child: Center(
                                      child: Text('${idx + 1}', style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                                    ),
                                  ),
                                );
                              }),
                            ],
                          );
                        },
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('已点击 ${_clicks.length}/${_captchaData!.targets.length}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                      TextButton.icon(
                        icon: const Icon(Icons.refresh, size: 16),
                        label: const Text('refresh'),
                        onPressed: _loadCaptcha,
                      ),
                    ],
                  ),
                ] else
                  const CircularProgressIndicator(),
                const SizedBox(height: 16),

                // Error
                if (_error != null) ...[
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(color: Colors.red[50], borderRadius: BorderRadius.circular(6)),
                    child: Row(children: [
                      const Icon(Icons.error_outline, color: Colors.red, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13))),
                    ]),
                  ),
                  const SizedBox(height: 12),
                ],

                // Login button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton(
                    onPressed: _loading ? null : _login,
                    child: _loading
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Text('${AppTranslations.t('login.login')}', style: TextStyle(fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 20),

                Text('Copyright (c) 2026 erik — https://erik.xyz',
                    style: TextStyle(fontSize: 11, color: Colors.grey[400])),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
