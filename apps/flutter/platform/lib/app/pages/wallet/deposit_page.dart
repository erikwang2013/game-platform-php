// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import 'wallet_amount.dart';

class DepositPage extends StatefulWidget {
  const DepositPage({super.key});

  @override
  State<DepositPage> createState() => _DepositPageState();
}

class _DepositPageState extends State<DepositPage> {
  final _api = ApiService();
  final _amountCtrl = TextEditingController();
  String _currency = 'USD';
  String? _methodId;
  List<Map<String, dynamic>> _methods = [];
  bool _methodsLoading = true;
  String? _checkoutUrl;
  bool _loading = false;
  String? _error;
  String? _orderNo;
  bool _success = false;

  // 服务端 currency 白名单；JPY/KRW 为 0 位小数币
  static const _currencies = ['USD', 'CNY', 'EUR', 'JPY', 'KRW', 'GBP', 'BRL', 'INR'];
  static const _zeroDecimalCurrencies = {'JPY', 'KRW'};

  @override
  void initState() {
    super.initState();
    _fetchMethods();
  }

  /// 支付方式由服务端下发：id 为 hashid，须原样回传为 payment_method_id
  Future<void> _fetchMethods() async {
    setState(() {
      _methodsLoading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/v1/payment/methods');
      final list = (resp['data']?['list'] as List?) ?? const [];
      final methods = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      setState(() {
        _methods = methods;
        _methodId = methods.isEmpty ? null : methods.first['id']?.toString();
        _methodsLoading = false;
        if (methods.isEmpty) _error = "${AppTranslations.t('deposit.no_methods')}";
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _methodsLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = "${AppTranslations.t('app.network_error')}";
        _methodsLoading = false;
      });
    }
  }

  Map<String, dynamic>? get _selectedMethod {
    for (final m in _methods) {
      if (m['id']?.toString() == _methodId) return m;
    }
    return null;
  }

  /// 下拉项直接展示区间（max_amount 数值为 0 表示无上限），避免用户猜限额
  String _methodLabel(Map<String, dynamic> m) {
    final min = displayAmount(m['min_amount']);
    final max = m['max_amount']?.toString() ?? '0';
    final range = isZeroAmount(max) ? '$min+' : '$min - ${displayAmount(max)}';
    return '${m['name']} ($range)';
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  Future<void> _openCheckout() async {
    final url = _checkoutUrl;
    if (url == null) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  Future<void> _submit() async {
    final amountText = _amountCtrl.text.trim();
    final method = _selectedMethod;
    if (amountText.isEmpty) {
      setState(() => _error = "${AppTranslations.t('deposit.enter_amount')}");
      return;
    }
    if (!isValidAmount(amountText,
        maxDecimals: _zeroDecimalCurrencies.contains(_currency) ? 0 : 2)) {
      setState(() => _error = "${AppTranslations.t('deposit.invalid_amount')}");
      return;
    }
    if (method == null) {
      setState(() => _error = "${AppTranslations.t('deposit.no_methods')}");
      return;
    }
    final min = method['min_amount']?.toString() ?? '0';
    final max = method['max_amount']?.toString() ?? '0';
    if (compareAmounts(amountText, min) < 0) {
      setState(() => _error = '${AppTranslations.t('deposit.amount_below_min')} $min');
      return;
    }
    if (!isZeroAmount(max) && compareAmounts(amountText, max) > 0) {
      setState(() => _error = '${AppTranslations.t('deposit.amount_above_max')} ${displayAmount(max)}');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _orderNo = null;
      _checkoutUrl = null;
      _success = false;
    });

    try {
      final resp = await _api.post('/api/v1/deposit/create', data: {
        'amount': amountText,
        'currency': _currency,
        'payment_method_id': method['id'],
      });
      final data = resp['data'];
      final url = data?['checkout_url']?.toString();
      setState(() {
        _orderNo = data?['order_no']?.toString();
        _checkoutUrl = (url == null || url.isEmpty) ? null : url;
        _success = true;
        _loading = false;
      });
      // 创建成功即跳支付页；浏览器拦截弹窗时用户可点成功区的按钮再开
      await _openCheckout();
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = "${AppTranslations.t('app.network_error')}";
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text("${AppTranslations.t('deposit.title')}"),
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
                      Text("${AppTranslations.t('deposit.title')}", style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text("${AppTranslations.t('deposit.subtitle')}", style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant)),
                      const SizedBox(height: 24),

                      // Amount
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: InputDecoration(
                          labelText: "${AppTranslations.t('deposit.amount')}",
                          prefixIcon: Icon(Icons.monetization_on_outlined),
                          border: OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Currency dropdown
                      DropdownButtonFormField<String>(
                        initialValue: _currency,
                        decoration: InputDecoration(
                          labelText: "${AppTranslations.t('deposit.currency')}",
                          prefixIcon: Icon(Icons.currency_exchange),
                          border: OutlineInputBorder(),
                        ),
                        items: _currencies.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                        onChanged: (v) => setState(() => _currency = v ?? 'USD'),
                      ),
                      const SizedBox(height: 16),

                      // Payment method dropdown（服务端下发）
                      DropdownButtonFormField<String>(
                        initialValue: _methodId,
                        decoration: InputDecoration(
                          labelText: "${AppTranslations.t('deposit.method')}",
                          prefixIcon: Icon(Icons.payment),
                          border: OutlineInputBorder(),
                        ),
                        items: _methods
                            .map((m) => DropdownMenuItem(
                                  value: m['id']?.toString(),
                                  child: Text(_methodLabel(m), overflow: TextOverflow.ellipsis),
                                ))
                            .toList(),
                        onChanged: _methodsLoading
                            ? null
                            : (v) => setState(() => _methodId = v),
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
                              Text("${AppTranslations.t('deposit.success')}", style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.green)),
                              const SizedBox(height: 8),
                              Text('${AppTranslations.t('deposit.order_no')}: $_orderNo', style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant)),
                              if (_checkoutUrl != null) ...[
                                const SizedBox(height: 12),
                                SizedBox(
                                  height: 40,
                                  child: FilledButton.icon(
                                    onPressed: _openCheckout,
                                    icon: const Icon(Icons.open_in_new, size: 18),
                                    label: Text("${AppTranslations.t('deposit.pay_now')}"),
                                  ),
                                ),
                              ],
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
                              : Text("${AppTranslations.t('deposit.submit')}", style: TextStyle(fontSize: 16)),
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
