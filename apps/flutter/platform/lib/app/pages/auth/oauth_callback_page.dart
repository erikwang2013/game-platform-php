// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';

class OAuthCallbackPage extends StatefulWidget {
  const OAuthCallbackPage({super.key});

  @override
  State<OAuthCallbackPage> createState() => _OAuthCallbackPageState();
}

class _OAuthCallbackPageState extends State<OAuthCallbackPage> {
  String? _error;

  @override
  void initState() {
    super.initState();
    // TODO(mobile deep-link): 当前仅处理 web 的 URL query。移动端 OAuth 回调需：
    // 1. 添加 app_links 依赖；2. AndroidManifest / iOS URL Scheme 注册回调 URL；
    // 3. 在 main() 监听 AppLinks().uriLinkStream，提取 code/state 后
    //    Get.toNamed('/oauth/callback', parameters: {...}) 复用本页逻辑。
    WidgetsBinding.instance.addPostFrameCallback((_) => _complete());
  }

  Map<String, String> _query() {
    final params = <String, String>{};
    Get.parameters.forEach((key, value) {
      if (value != null && value.isNotEmpty) params[key] = value;
    });
    if (kIsWeb) {
      params.addAll(Uri.base.queryParameters);
    }
    return params;
  }

  Future<void> _complete() async {
    final params = _query();
    final code = params['code'];
    final state = params['state'];
    final provider = params['provider'] ?? await AuthService.takePendingOauthProvider();

    if (code == null || code.isEmpty || state == null || state.isEmpty || provider == null || provider.isEmpty) {
      setState(() => _error = '${AppTranslations.t('oauth.failed')}');
      return;
    }

    try {
      final api = ApiService();
      final resp = await api.post('/api/v1/auth/oauth/$provider/callback', data: {
        'code': code,
        'state': state,
      });
      final data = resp['data'] ?? {};
      await AuthService.saveLogin(
        token: data['access_token'] as String,
        refreshToken: data['refresh_token'] as String,
        username: data['user']?['username'] as String? ?? '',
      );
      if (data['is_new'] == true) {
        Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('login.welcome_new')}');
      }
      Get.offAllNamed('/games');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = '${AppTranslations.t('oauth.failed')}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: _error == null
            ? Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const CircularProgressIndicator(),
                  const SizedBox(height: 16),
                  Text('${AppTranslations.t('oauth.processing')}'),
                ],
              )
            : Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () => Get.offAllNamed('/login'),
                    child: Text('${AppTranslations.t('login.go_login')}'),
                  ),
                ],
              ),
      ),
    );
  }
}
