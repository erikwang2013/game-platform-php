// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class DepositPage extends StatefulWidget {
  const DepositPage({super.key});

  @override
  State<DepositPage> createState() => _DepositPageState();
}

class _DepositPageState extends State<DepositPage> {
  final _api = ApiService();
  final _amountCtrl = TextEditingController();
  String _currency = 'USD';
  String _method = 'stripe';
  bool _loading = false;
  String? _error;
  String? _orderNo;
  bool _success = false;

  final _currencies = ['USD', 'CNY', 'EUR'];
  final _methods = {'stripe': 'Stripe (USD)', 'paypal': 'PayPal', 'alipay': '支付宝'};

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amountText = _amountCtrl.text.trim();
    if (amountText.isEmpty) {
      setState(() => _error = '请输入充值金额');
      return;
    }
    final amount = double.tryParse(amountText);
    if (amount == null || amount <= 0) {
      setState(() => _error = '请输入有效的金额');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _orderNo = null;
      _success = false;
    });

    try {
      final resp = await _api.post('/api/deposit/create', data: {
        'amount': amount,
        'currency': _currency,
        'payment_method': _method,
      });
      final data = resp['data'];
      setState(() {
        _orderNo = data?['order_no']?.toString();
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
        title: const Text('充值'),
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
                      Text('账户充值', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text('选择充值金额和支付方式', style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant)),
                      const SizedBox(height: 24),

                      // Amount
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(
                          labelText: '充值金额',
                          prefixIcon: Icon(Icons.monetization_on_outlined),
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Currency dropdown
                      DropdownButtonFormField<String>(
                        initialValue: _currency,
                        decoration: const InputDecoration(
                          labelText: '币种',
                          prefixIcon: Icon(Icons.currency_exchange),
                          border: OutlineInputBorder(),
                        ),
                        items: _currencies.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                        onChanged: (v) => setState(() => _currency = v ?? 'USD'),
                      ),
                      const SizedBox(height: 16),

                      // Payment method dropdown
                      DropdownButtonFormField<String>(
                        initialValue: _method,
                        decoration: const InputDecoration(
                          labelText: '支付方式',
                          prefixIcon: Icon(Icons.payment),
                          border: OutlineInputBorder(),
                        ),
                        items: _methods.entries
                            .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                            .toList(),
                        onChanged: (v) => setState(() => _method = v ?? 'stripe'),
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
                      if (_success && _orderNo != null) ...[
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
                              const Text('充值申请已提交', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.green)),
                              const SizedBox(height: 8),
                              Text('订单号: $_orderNo', style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Submit button
                      SizedBox(
                        height: 44,
                        child: FilledButton(
                          onPressed: _loading ? null : _submit,
                          child: _loading
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('提交充值', style: TextStyle(fontSize: 16)),
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
