// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import '../../i18n/locale_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

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
  String? _selectedCurrencyCode;
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
      final resp = await _api.get('/api/game/list');
      final data = resp['data'];
      if (mounted) {
        setState(() {
          _games = data is List ? List<Map<String, dynamic>>.from(data) : [];
        });
      }
    } catch (_) {}
  }

  void _onGameChanged(String? gameId) {
    setState(() {
      _selectedGameId = gameId;
      _selectedCurrencyCode = null;
      _quote = null;
      _result = null;
      if (gameId != null) {
        _fetchGameCurrencies(int.tryParse(gameId) ?? 0);
      } else {
        _gameCurrencies = [];
      }
    });
  }

  Future<void> _fetchGameCurrencies(int gameId) async {
    // For now, derive from game list data
    final game = _games.firstWhere(
      (g) => g['id']?.toString() == gameId.toString(),
      orElse: () => {},
    );
    if (game.isNotEmpty && game['currencies'] is List) {
      setState(() {
        _gameCurrencies = List<Map<String, dynamic>>.from(game['currencies']);
      });
    }
  }

  Future<void> _getQuote() async {
    final amountText = _amountCtrl.text.trim();
    if (amountText.isEmpty) {
      setState(() => _error = '${AppTranslations.t('deposit.invalid_amount')}');
      return;
    }
    final amount = double.tryParse(amountText);
    if (amount == null || amount <= 0) {
      setState(() => _error = "${AppTranslations.t('deposit.invalid_amount')}");
      return;
    }

    setState(() {
      _quoting = true;
      _error = null;
      _quote = null;
      _result = null;
      _success = false;
    });

    try {
      final resp = await _api.post('/api/exchange/quote', data: {
        'game_id': _selectedGameId,
        'amount': amount,
        'direction': _isBuying ? 'buy' : 'sell',
        'currency_code': _selectedCurrencyCode,
      });
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

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final endpoint = _isBuying ? '/api/exchange/buy' : '/api/exchange/sell';
      final resp = await _api.post(endpoint, data: {
        'game_id': _selectedGameId,
        'amount': double.tryParse(_amountCtrl.text.trim()),
        'direction': _isBuying ? 'buy' : 'sell',
        'currency_code': _selectedCurrencyCode,
        'quote_id': _quote!['quote_id'],
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
                          initialValue: _selectedCurrencyCode,
                          decoration: InputDecoration(
                            labelText: "${AppTranslations.t('exchange.select_currency')}",
                            border: OutlineInputBorder(),
                          ),
                          items: _gameCurrencies.map((c) {
                            return DropdownMenuItem(
                              value: c['code']?.toString() ?? c['name']?.toString(),
                              child: Text('${c['name'] ?? c['code']} (Rate:  ${c['exchange_rate'] ?? '-'})'),
                            );
                          }).toList(),
                          onChanged: (v) => setState(() => _selectedCurrencyCode = v),
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Amount
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
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
                              _buildQuoteRow('${AppTranslations.t('exchange.from_amount')}', _quote!['from_amount']?.toString() ?? '-'),
                              _buildQuoteRow('${AppTranslations.t('exchange.to_amount')}', _quote!['to_amount']?.toString() ?? '-'),
                              _buildQuoteRow('${AppTranslations.t('exchange.fee')}', _quote!['fee']?.toString() ?? '0'),
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
                              Text('${AppTranslations.t('exchange.order_no')}: ${_result!['order_no'] ?? '-'}', style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant)),
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
