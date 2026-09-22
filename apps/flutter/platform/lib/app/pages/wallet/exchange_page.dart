// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import 'wallet_amount.dart';

class ExchangePage extends StatefulWidget {
  const ExchangePage({super.key});

  @override
  State<ExchangePage> createState() => _ExchangePageState();
}

class _ExchangePageState extends State<ExchangePage> {
  final _api = ApiService();
  final _amountCtrl = TextEditingController();
  List<Map<String, dynamic>> _games = [];
  String? _selectedGameId;
  String? _selectedCurrencyId;
  List<Map<String, dynamic>> _gameCurrencies = [];
  bool _isBuying = true;
  bool _loading = false;
  bool _quoting = false;
  String? _error;
  Map<String, dynamic>? _quote;
  Map<String, dynamic>? _result;
  bool _success = false;

  @override
  void initState() {
    super.initState();
    _fetchGames();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchGames() async {
    try {
      final resp = await _api.get('/api/v1/game/list');
      final data = resp['data'];
      final items = data is Map ? data['items'] : null;
      if (mounted) {
        setState(() {
          _games = items is List ? List<Map<String, dynamic>>.from(items) : [];
        });
      }
    } catch (_) {}
  }

  void _onGameChanged(String? gameId) {
    final game = _games.firstWhere(
      (g) => g['id']?.toString() == gameId,
      orElse: () => const {},
    );
    final currencies = game['currencies'] is List
        ? List<Map<String, dynamic>>.from(game['currencies'])
        : <Map<String, dynamic>>[];
    setState(() {
      _selectedGameId = gameId;
      _gameCurrencies = currencies;
      _selectedCurrencyId = currencies.isEmpty ? null : currencies.first['id']?.toString();
      _quote = null;
      _result = null;
    });
  }

  /// 询价与下单同一请求体：direction in=平台→游戏 / out=游戏→平台；
  /// out 时 platform_amount 承载的是"游戏币"数量（字段名不变），buy/sell 均无 quote_id
  Map<String, dynamic> _requestBody(String amountText) => {
        'game_id': _selectedGameId,
        'currency_id': _selectedCurrencyId,
        'direction': _isBuying ? 'in' : 'out',
        'platform_amount': amountText,
      };

  /// 返回校验通过的金额字符串，错误时置 _error 并返回 null
  String? _validatedAmount() {
    final amountText = _amountCtrl.text.trim();
    if (!isPositiveAmount(amountText)) {
      setState(() => _error = "${AppTranslations.t('deposit.invalid_amount')}");
      return null;
    }
    if (_selectedGameId == null || _selectedCurrencyId == null) {
      setState(() => _error = "${AppTranslations.t('exchange.select_game')}");
      return null;
    }
    return amountText;
  }

  Future<void> _getQuote() async {
    final amountText = _validatedAmount();
    if (amountText == null) return;

    setState(() {
      _quoting = true;
      _error = null;
      _quote = null;
      _result = null;
      _success = false;
    });

    try {
      final resp = await _api.post('/api/v1/exchange/quote', data: _requestBody(amountText));
      if (!mounted) return;
      // 请求期间数量又被改过：这份报价对应的是旧数量，直接丢弃（同 React 的 fresh 判断）
      if (_amountCtrl.text.trim() != amountText) {
        setState(() => _quoting = false);
        return;
      }
      setState(() {
        _quote = resp['data'];
        _quoting = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _quoting = false;
      });
    } catch (e) {
      setState(() {
        _error = "${AppTranslations.t('app.network_error')}";
        _quoting = false;
      });
    }
  }

  Future<void> _confirmExchange() async {
    if (_quote == null) return;

    final amountText = _validatedAmount();
    if (amountText == null) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final endpoint = _isBuying ? '/api/v1/exchange/buy' : '/api/v1/exchange/sell';
      final resp = await _api.post(endpoint, data: _requestBody(amountText));
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
        title: Text("${AppTranslations.t('exchange.title')}"),
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
                      Text("${AppTranslations.t('exchange.title')}", style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text("${AppTranslations.t('exchange.subtitle')}", style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant)),
                      const SizedBox(height: 24),

                      // Direction toggle
                      Row(
                        children: [
                          Expanded(
                            child: _buildDirectionButton("${AppTranslations.t('exchange.buy')}", _isBuying, () => setState(() {
                              _isBuying = true;
                              _quote = null;
                              _result = null;
                            })),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _buildDirectionButton("${AppTranslations.t('exchange.sell')}", !_isBuying, () => setState(() {
                              _isBuying = false;
                              _quote = null;
                              _result = null;
                            })),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),

                      // Game selector
                      DropdownButtonFormField<String>(
                        initialValue: _selectedGameId,
                        decoration: InputDecoration(
                          labelText: "${AppTranslations.t('exchange.select_game')}",
                          border: OutlineInputBorder(),
                        ),
                        items: _games.map((g) {
                          return DropdownMenuItem(
                            value: g['id']?.toString(),
                            child: Text(g['name'] ?? ''),
                          );
                        }).toList(),
                        onChanged: _onGameChanged,
                      ),
                      const SizedBox(height: 16),

                      // Currency selector (if available)
                      if (_gameCurrencies.isNotEmpty) ...[
                        DropdownButtonFormField<String>(
                          initialValue: _selectedCurrencyId,
                          decoration: InputDecoration(
                            labelText: "${AppTranslations.t('exchange.select_currency')}",
                            border: OutlineInputBorder(),
                          ),
                          items: _gameCurrencies.map((c) {
                            return DropdownMenuItem(
                              value: c['id']?.toString(),
                              child: Text('${c['name'] ?? ''} ${c['symbol'] ?? ''} (Rate: ${c['exchange_rate'] ?? '-'})'),
                            );
                          }).toList(),
                          onChanged: (v) => setState(() {
                            _selectedCurrencyId = v;
                            _quote = null;
                            _result = null;
                          }),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Amount
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        // 数量一变旧报价即作废：报价卡与"确认兑换"按钮同时消失，必须重新询价
                        onChanged: (_) => setState(() => _quote = null),
                        decoration: InputDecoration(
                          labelText: _isBuying ? '${AppTranslations.t('exchange.payment_amount')}' : '${AppTranslations.t('exchange.sell_amount')}',
                          prefixIcon: const Icon(Icons.monetization_on_outlined),
                          border: const OutlineInputBorder(),
                        ),
                      ),
                      const SizedBox(height: 20),

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

                      // Quote result
                      if (_quote != null) ...[
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: colorScheme.primaryContainer.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: colorScheme.primary.withValues(alpha: 0.3)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text("${AppTranslations.t('exchange.quote_title')}", style: TextStyle(fontWeight: FontWeight.w600, color: colorScheme.primary)),
                              const SizedBox(height: 8),
                              _buildQuoteRow('${AppTranslations.t('exchange.rate')}', _quote!['rate']?.toString() ?? '-'),
                              _buildQuoteRow('${AppTranslations.t('exchange.from_amount')}', _quote!['platform_amount']?.toString() ?? '-'),
                              // in=平台→游戏 到手 actual_game_amount；out=游戏→平台 到手 actual_platform_amount
                              _buildQuoteRow(
                                '${AppTranslations.t('exchange.to_amount')}',
                                (_isBuying
                                        ? _quote!['actual_game_amount']
                                        : _quote!['actual_platform_amount'])
                                    ?.toString() ??
                                    '-',
                              ),
                              _buildQuoteRow('${AppTranslations.t('exchange.fee')}', _quote!['spread_fee']?.toString() ?? '0'),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Success result
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
                              Text("${AppTranslations.t('exchange.success')}", style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.green)),
                              const SizedBox(height: 8),
                              Text('${AppTranslations.t('exchange.order_no')}: ${_result!['exchange_id'] ?? '-'}', style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Action buttons
                      if (_quote == null && !_success) ...[
                        SizedBox(
                          height: 44,
                          child: FilledButton.tonal(
                            onPressed: _quoting ? null : _getQuote,
                            child: _quoting
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                : Text("${AppTranslations.t('exchange.get_quote')}", style: TextStyle(fontSize: 16)),
                          ),
                        ),
                      ],
                      if (_quote != null && !_success) ...[
                        SizedBox(
                          height: 44,
                          child: FilledButton(
                            onPressed: _loading ? null : _confirmExchange,
                            child: _loading
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : Text("${AppTranslations.t('exchange.confirm')}", style: TextStyle(fontSize: 16)),
                          ),
                        ),
                      ],
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

  Widget _buildDirectionButton(String label, bool active, VoidCallback onTap) {
    final colorScheme = Theme.of(context).colorScheme;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: active ? colorScheme.primary : colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: active ? colorScheme.primary : colorScheme.outline.withValues(alpha: 0.3)),
        ),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: active ? colorScheme.onPrimary : colorScheme.onSurface,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildQuoteRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 13)),
          Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
