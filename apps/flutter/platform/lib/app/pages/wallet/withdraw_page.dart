// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class WithdrawPage extends StatefulWidget {
  const WithdrawPage({super.key});

  @override
  State<WithdrawPage> createState() => _WithdrawPageState();
}

class _WithdrawPageState extends State<WithdrawPage> {
  final _api = ApiService();
  final _amountCtrl = TextEditingController();
  final _accountCtrl = TextEditingController();
  String _method = 'paypal';
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _result;
  bool _success = false;

  final _methods = {
    'paypal': 'PayPal',
    'bank': 'Bank Transfer',
    'crypto': 'Crypto Wallet',
  };

  // Limits - fetched from platform config, hardcoded defaults for MVP
  double _minAmount = 10;
  double _dailyLimit = 10000;

  @override
  void initState() {
    super.initState();
    _fetchLimits();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _accountCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchLimits() async {
    try {
      final resp = await _api.get('/api/config/withdraw_limits');
      final data = resp['data'];
      if (data != null && mounted) {
        setState(() {
          _minAmount = (data['min_amount'] ?? 10).toDouble();
          _dailyLimit = (data['daily_limit'] ?? 10000).toDouble();
        });
      }
    } catch (_) {
      // Use defaults
    }
  }

  Future<void> _submit() async {
    final amountText = _amountCtrl.text.trim();
    final accountInfo = _accountCtrl.text.trim();

    if (amountText.isEmpty) {
      setState(() => _error = '请输入提现金额');
      return;
    }
    final amount = double.tryParse(amountText);
    if (amount == null || amount <= 0) {
      setState(() => _error = '请输入有效的金额');
      return;
    }
    if (amount < _minAmount) {
      setState(() => _error = '最低提现金额为 $_minAmount');
      return;
    }
    if (amount > _dailyLimit) {
      setState(() => _error = '超过每日提现限额 $_dailyLimit');
      return;
    }
    if (accountInfo.isEmpty) {
      setState(() => _error = '请输入收款账户信息');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _result = null;
      _success = false;
    });

    try {
      final resp = await _api.post('/api/withdraw/apply', data: {
        'amount': amount,
        'method': _method,
        'account_info': accountInfo,
      });
      setState(() {
        _result = resp['data'];
        _success = true;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = '网络错误，请重试';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('提现'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
      ),
      body: Container(
        color: colorScheme.surfaceContainerLowest,
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('提现', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text('将平台余额提现到您的账户', style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant)),
                      const SizedBox(height: 20),

                      // Limits info
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: colorScheme.primaryContainer.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.info_outline, size: 18, color: colorScheme.primary),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                '最低提现: $_minAmount  |  每日限额: $_dailyLimit',
                                style: TextStyle(fontSize: 13, color: colorScheme.onPrimaryContainer),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Amount
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(
                          labelText: '提现金额',
                          prefixIcon: Icon(Icons.monetization_on_outlined),
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Method
                      DropdownButtonFormField<String>(
                        initialValue: _method,
                        decoration: const InputDecoration(
                          labelText: '提现方式',
                          prefixIcon: Icon(Icons.account_balance),
                          border: OutlineInputBorder(),
                        ),
                        items: _methods.entries
                            .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                            .toList(),
                        onChanged: (v) => setState(() => _method = v ?? 'paypal'),
                      ),
                      const SizedBox(height: 16),

                      // Account info
                      TextField(
                        controller: _accountCtrl,
                        decoration: InputDecoration(
                          labelText: _method == 'paypal'
                              ? 'PayPal 邮箱地址'
                              : _method == 'bank'
                                  ? '银行账户信息'
                                  : '加密货币地址',
                          prefixIcon: const Icon(Icons.edit),
                          border: const OutlineInputBorder(),
                        ),
                        maxLines: _method == 'bank' ? 3 : 1,
                      ),
                      const SizedBox(height: 24),

                      // Error
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

                      // Success
                      if (_success && _result != null) ...[
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.green.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Column(
                            children: [
                              const Icon(Icons.check_circle, color: Colors.green, size: 40),
                              const SizedBox(height: 8),
                              const Text('提现申请已提交', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.green)),
                              const SizedBox(height: 8),
                              Text(
                                '状态: ${_result!['status'] ?? 'pending'}',
                                style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Submit
                      SizedBox(
                        height: 44,
                        child: FilledButton(
                          onPressed: _loading ? null : _submit,
                          child: _loading
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('提交提现申请', style: TextStyle(fontSize: 16)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
