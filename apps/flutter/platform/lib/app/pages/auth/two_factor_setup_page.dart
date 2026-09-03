// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';

class TwoFactorSetupPage extends StatefulWidget {
  const TwoFactorSetupPage({super.key});

  @override
  State<TwoFactorSetupPage> createState() => _TwoFactorSetupPageState();
}

class _TwoFactorSetupPageState extends State<TwoFactorSetupPage> {
  final _api = ApiService();
  final _codeCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _loading = true;
  bool _busy = false;
  bool _enabled = false;
  String? _secret;
  String? _qrUrl;
  List<dynamic> _backupCodes = [];
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/v1/user/2fa/status');
      setState(() {
        _enabled = resp['data']?['enabled'] == true;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      setState(() {
        _error = '${AppTranslations.t('app.loading_failed')}';
        _loading = false;
      });
    }
  }

  Future<void> _setup() async {
    setState(() {
      _busy = true;
      _error = null;
      _success = null;
    });
    try {
      final resp = await _api.post('/api/v1/user/2fa/setup');
      final data = resp['data'] ?? {};
      setState(() {
        _secret = data['secret']?.toString();
        _qrUrl = data['qr_url']?.toString();
        _busy = false;
      });
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

  Future<void> _enable() async {
    final code = _codeCtrl.text.trim();
    if (code.length != 6) {
      setState(() => _error = '${AppTranslations.t('two_factor.enter_code')}');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _success = null;
    });
    try {
      final resp = await _api.post('/api/v1/user/2fa/enable', data: {'code': code});
      setState(() {
        _enabled = true;
        _backupCodes = resp['data']?['backup_codes'] as List<dynamic>? ?? [];
        _success = '${AppTranslations.t('two_factor.enabled')}';
        _busy = false;
        _secret = null;
        _qrUrl = null;
      });
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

  Future<void> _disable() async {
    final code = _codeCtrl.text.trim();
    final password = _passwordCtrl.text;
    if (code.length != 6 || password.isEmpty) {
      setState(() => _error = '${AppTranslations.t('two_factor.enter_code')}');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _success = null;
    });
    try {
      await _api.post('/api/v1/user/2fa/disable', data: {
        'code': code,
        'password': password,
      });
      setState(() {
        _enabled = false;
        _backupCodes = [];
        _success = '${AppTranslations.t('two_factor.disabled')}';
        _busy = false;
      });
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
      appBar: AppBar(
        title: Text('${AppTranslations.t('two_factor.title')}'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 560),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Card(
                      child: ListTile(
                        leading: Icon(_enabled ? Icons.verified_user : Icons.security, color: _enabled ? Colors.green : null),
                        title: Text(_enabled
                            ? '${AppTranslations.t('two_factor.enabled')}'
                            : '${AppTranslations.t('two_factor.disabled')}'),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (_error != null) _banner(_error!, Colors.red),
                    if (_success != null) _banner(_success!, Colors.green),
                    if (!_enabled && _secret == null)
                      FilledButton(
                        onPressed: _busy ? null : _setup,
                        child: Text('${AppTranslations.t('two_factor.setup')}'),
                      ),
                    if (_secret != null) ...[
                      Text('${AppTranslations.t('two_factor.setup_hint')}'),
                      const SizedBox(height: 8),
                      SelectableText(_secret!, style: const TextStyle(fontFamily: 'monospace', fontSize: 16)),
                      TextButton.icon(
                        onPressed: () => Clipboard.setData(ClipboardData(text: _secret!)),
                        icon: const Icon(Icons.copy, size: 16),
                        label: Text('${AppTranslations.t('two_factor.secret')}'),
                      ),
                      if (_qrUrl != null) SelectableText(_qrUrl!, style: const TextStyle(fontSize: 12)),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _codeCtrl,
                        keyboardType: TextInputType.number,
                        maxLength: 6,
                        decoration: InputDecoration(
                          labelText: '${AppTranslations.t('two_factor.code')}',
                          border: const OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: _busy ? null : _enable,
                        child: Text('${AppTranslations.t('two_factor.enable')}'),
                      ),
                    ],
                    if (_enabled) ...[
                      TextField(
                        controller: _passwordCtrl,
                        obscureText: true,
                        decoration: InputDecoration(
                          labelText: '${AppTranslations.t('two_factor.password')}',
                          border: const OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _codeCtrl,
                        keyboardType: TextInputType.number,
                        maxLength: 6,
                        decoration: InputDecoration(
                          labelText: '${AppTranslations.t('two_factor.code')}',
                          border: const OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton(
                        onPressed: _busy ? null : _disable,
                        style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                        child: Text('${AppTranslations.t('two_factor.disable')}'),
                      ),
                    ],
                    if (_backupCodes.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Text('${AppTranslations.t('two_factor.backup_codes')}',
                          style: const TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      ..._backupCodes.map((c) => Text('$c', style: const TextStyle(fontFamily: 'monospace'))),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  Widget _banner(String text, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(6),
        ),
        child: Text(text, style: TextStyle(color: color, fontSize: 13)),
      ),
    );
  }
}
