// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';

class TwoFactorVerifyPage extends StatefulWidget {
  const TwoFactorVerifyPage({super.key});

  @override
  State<TwoFactorVerifyPage> createState() => _TwoFactorVerifyPageState();
}

class _TwoFactorVerifyPageState extends State<TwoFactorVerifyPage> {
  final _api = ApiService();
  final _codeCtrl = TextEditingController();
  String? _ticket;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments;
    if (args is Map && args['pending_2fa_token'] != null) {
      _ticket = '${args['pending_2fa_token']}';
    }
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final ticket = _ticket;
    final code = _codeCtrl.text.trim();
    if (ticket == null || ticket.isEmpty || code.length != 6) {
      setState(() => _error = '${AppTranslations.t('two_factor.enter_code')}');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final resp = await _api.post('/api/v1/2fa/verify', data: {'pending_2fa_token': ticket, 'code': code});
      final data = resp['data'];
      await AuthService.saveLogin(
        token: data['access_token'] as String,
        refreshToken: data['refresh_token'] as String,
        username: data['user']['username'] as String,
      );
      if (mounted) Get.offAllNamed('/games');
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _busy = false;
      });
    } catch (_) {
      setState(() {
        _error = '${AppTranslations.t('app.network_error')}';
        _busy = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('${AppTranslations.t('two_factor.verify_title')}')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 400),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: _codeCtrl,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: InputDecoration(
                    labelText: '${AppTranslations.t('two_factor.code')}',
                    border: const OutlineInputBorder(),
                  ),
                  onSubmitted: (_) => _verify(),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                ],
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: FilledButton(
                    onPressed: _busy ? null : _verify,
                    child: _busy
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Text('${AppTranslations.t('two_factor.verify')}'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
