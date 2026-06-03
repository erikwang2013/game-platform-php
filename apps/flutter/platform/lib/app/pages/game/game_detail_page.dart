// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import '../../i18n/locale_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

class GameDetailPage extends StatefulWidget {
  const GameDetailPage({super.key});

  @override
  State<GameDetailPage> createState() => _GameDetailPageState();
}

class _GameDetailPageState extends State<GameDetailPage> {
  final _api = ApiService();
  late Map<String, dynamic> _game;
  bool _loading = false;
  String? _resultMsg;
  bool _resultSuccess = false;

  @override
  void initState() {
    super.initState();
    _game = Get.arguments as Map<String, dynamic>;
  }

  Future<void> _launchGame() async {
    setState(() {
      _loading = true;
      _resultMsg = null;
    });

    try {
      final resp = await _api.post('/api/game/launch', data: {'game_id': _game['id']});
      final data = resp['data'];
      setState(() {
        _resultMsg = data?['message'] ?? 'Game launched successfully';
        _resultSuccess = true;
        _loading = false;
      });
      // If a game URL is returned, navigate to it
      if (data?['url'] != null) {
        // In web, we could open a new window or iframe
      }
    } on ApiException catch (e) {
      setState(() {
        _resultMsg = e.message;
        _resultSuccess = false;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _resultMsg = "${AppTranslations.t('app.network_error')}";
        _resultSuccess = false;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final name = _game['name'] ?? 'Game';
    final description = _game['description'] ?? '';
    final type = _game['type'] ?? _game['game_type'] ?? '';
    final currencies = _game['currencies'];

    return Scaffold(
      appBar: AppBar(
        title: Text(name),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Get.back(),
        ),
      ),
      body: Container(
        color: colorScheme.surfaceContainerLowest,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1200),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Game header
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Cover placeholder
                        Container(
                          width: 200,
                          height: 150,
                          decoration: BoxDecoration(
                            color: colorScheme.primaryContainer.withValues(alpha: 0.3),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Center(
                            child: Icon(Icons.sports_esports, size: 64, color: colorScheme.primary.withValues(alpha: 0.5)),
                          ),
                        ),
                        const SizedBox(width: 24),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Text(name, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                                  const SizedBox(width: 12),
                                  if (type.isNotEmpty)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: colorScheme.secondaryContainer,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(type, style: TextStyle(color: colorScheme.onSecondaryContainer)),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              Text(description, style: TextStyle(fontSize: 14, color: colorScheme.onSurfaceVariant)),
                              const SizedBox(height: 20),
                              SizedBox(
                                height: 44,
                                child: FilledButton.icon(
                                  onPressed: _loading ? null : _launchGame,
                                  icon: _loading
                                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                      : const Icon(Icons.play_arrow),
                                  label: Text("${AppTranslations.t('game_detail.start_game')}", style: TextStyle(fontSize: 16)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),

                // Result message
                if (_resultMsg != null) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _resultSuccess ? Colors.green.withValues(alpha: 0.1) : Colors.red.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          _resultSuccess ? Icons.check_circle : Icons.error_outline,
                          color: _resultSuccess ? Colors.green : Colors.red,
                          size: 20,
                        ),
                        const SizedBox(width: 8),
                        Text(_resultMsg!, style: TextStyle(color: _resultSuccess ? Colors.green : Colors.red)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                ],

                // Currencies / exchange rates
                if (currencies != null && currencies is List && currencies.isNotEmpty) ...[
                  Text("${AppTranslations.t('game_detail.supported_currencies')}", style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 12),
                  Card(
                    child: DataTable(
                      columns: [
                        DataColumn(label: Text('${AppTranslations.t("game_detail.supported_currencies")}')),
                        DataColumn(label: Text('${AppTranslations.t("game_detail.exchange_rate")}')),
                        DataColumn(label: Text('${AppTranslations.t("game_detail.description")}')),
                      ],
                      rows: currencies.map((c) {
                        final cMap = c as Map<String, dynamic>?;
                        return DataRow(cells: [
                          DataCell(Text(cMap?['name'] ?? cMap?['code'] ?? '-')),
                          DataCell(Text(cMap?['exchange_rate']?.toString() ?? '-')),
                          DataCell(Text(cMap?['description'] ?? '-')),
                        ]);
                      }).toList(),
                    ),
                  ),
                ],

                const SizedBox(height: 24),

                // Back button
                OutlinedButton.icon(
                  onPressed: () => Get.back(),
                  icon: const Icon(Icons.arrow_back),
                  label: Text("${AppTranslations.t('game_detail.back_to_hall')}"),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
