// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;
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
  static final _headers = <String, dynamic>{};
  final _dio = Dio(BaseOptions(baseUrl: ApiService.baseUrl, headers: _headers));
  final _captcha = CaptchaService(Dio(BaseOptions(baseUrl: ApiService.baseUrl, headers: _headers)));

  bool _loading = false;
  String? _error;

  // Captcha state
  CaptchaData? _captchaData;
  Uint8List? _captchaImage;
  /// 服务端画布真实尺寸，点击坐标必须按它换算（硬编码会被服务端判错）
  Size? _imgSize;
  final List<Offset> _clicks = [];

  @override
  void initState() {
    super.initState();
    _loadCaptcha();
  }

  Future<void> _loadCaptcha() async {
    try {
      final data = await _captcha.generate();
      final bytes = base64Decode(data.imageBase64.replaceFirst(RegExp(r'^data:image/\w+;base64,'), ''));
      final frame = await (await ui.instantiateImageCodec(bytes)).getNextFrame();
      if (!mounted) return;
      setState(() {
        _captchaData = data;
        _captchaImage = bytes;
        _imgSize = Size(frame.image.width.toDouble(), frame.image.height.toDouble());
        _clicks.clear();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'captchaLoadFailed');
    }
  }

  /// 显示坐标 → 服务端画布坐标（显示框宽高比与画布一致，无留白，线性映射即精确）
  void _onCaptchaTap(TapUpDetails detail, Size box) {
    final img = _imgSize;
    final data = _captchaData;
    if (data == null || img == null || box.width == 0 || box.height == 0) return;
    if (_clicks.length >= data.targets.length) return;
    setState(() {
      _clicks.add(Offset(
        (detail.localPosition.dx / box.width * img.width).roundToDouble(),
        (detail.localPosition.dy / box.height * img.height).roundToDouble(),
      ));
      _error = null;
    });
  }

  /// 账号密码齐才开框；验证码一次性，失败后重取
  Future<void> _login() async {
    final username = _usernameCtrl.text.trim();
    final password = _passwordCtrl.text;

    if (username.isEmpty || password.isEmpty) {
      setState(() => _error = 'enterCredentials');
      return;
    }
    if (_captchaData == null || _captchaImage == null) {
      setState(() => _error = 'loadCaptcha');
      return;
    }

    final confirmed = await showDialog<bool>(context: context, builder: _captchaDialog);
    if (confirmed != true || !mounted) return;
    await _submit(username, password);
  }

  Widget _captchaDialog(BuildContext dialogContext) {
    final data = _captchaData!;
    return StatefulBuilder(
      builder: (context, setLocal) => AlertDialog(
        title: const Text('安全验证'),
        content: SizedBox(
          width: 400,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '请按顺序点击图中文字: ${data.targets.map((t) => '"${t.text}"').join(' → ')}',
                style: const TextStyle(fontSize: 13, color: Colors.black87),
              ),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final w = constraints.maxWidth;
                    final img = _imgSize!;
                    final h = w * img.height / img.width;
                    return GestureDetector(
                      onTapUp: (d) => setLocal(() => _onCaptchaTap(d, Size(w, h))),
                      child: Stack(
                        children: [
                          Image.memory(_captchaImage!, width: w, height: h, fit: BoxFit.fill),
                          ..._clicks.asMap().entries.map((entry) {
                            final idx = entry.key;
                            final c = entry.value;
                            return Positioned(
                              left: (c.dx / img.width) * w - 14,
                              top: (c.dy / img.height) * h - 14,
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
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Text('已点击 ${_clicks.length}/${data.targets.length}', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                  const Spacer(),
                  TextButton.icon(
                    icon: const Icon(Icons.refresh, size: 16),
                    label: const Text('换一张'),
                    onPressed: () async {
                      await _loadCaptcha();
                      setLocal(() {});
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('取消'),
          ),
          FilledButton(
            onPressed: _clicks.length >= data.targets.length
                ? () => Navigator.pop(dialogContext, true)
                : null,
            child: const Text('确认登录'),
          ),
        ],
      ),
    );
  }

  Future<void> _submit(String username, String password) async {
    setState(() => _loading = true);

    try {
      final resp = await _dio.post('/api/v1/auth/login', data: {
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
        if (!mounted) return;
        setState(() => _error = resp.data['message'] ?? 'loginFailed');
        _loadCaptcha();
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = 'networkErrorCheck');
      _loadCaptcha();
    } finally {
      if (mounted) setState(() => _loading = false);
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
                // Logo area —— 吉祥物「小骰」(Dicey)
                Image.asset('assets/mascot.png', width: 96),
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

                // Login button —— 点击后弹验证码框
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
