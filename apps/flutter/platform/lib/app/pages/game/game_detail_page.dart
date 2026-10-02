// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class GameDetailPage extends StatefulWidget {
  const GameDetailPage({super.key});

  @override
  State<GameDetailPage> createState() => _GameDetailPageState();
}

class _GameDetailPageState extends State<GameDetailPage> {
  final _api = ApiService();
  Map<String, dynamic> _game = const {};
  /// 深链（F5/直接打开链接）时手里只有 hashid，没有列表页那份完整对象
  String? _hashid;
  bool _loading = false;
  bool _detailLoading = false;
  String? _loadError;
  String? _resultMsg;
  bool _resultSuccess = false;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments;
    if (args is Map) {
      _game = Map<String, dynamic>.from(args);
      _hashid = _game['id']?.toString();
      return;
    }
    // 深链/刷新：Get.arguments 只在当次导航的内存里活着，此处为 null。
    // 旧代码直接 `args as Map<String, dynamic>` 非空转换 ⇒ TypeError ⇒ 白屏且无出口。
    // 自救路径：URL 上的 hashid + 现成的 GET /api/v1/game/detail/{hashid}；
    // 连 hashid 都没有（例如用户手敲 /game-detail）才走有出口的错误态。
    final id = deepLinkParam('id');
    if (id == null) {
      setState(() => _loadError = '${AppTranslations.t('app.loading_failed')}');
      return;
    }
    _hashid = id;
    _loadDetail(id);
  }

  Future<void> _loadDetail(String hashid) async {
    setState(() {
      _detailLoading = true;
      _loadError = null;
    });
    try {
      final resp = await _api.get('/api/v1/game/detail/$hashid');
      final data = resp['data'];
      if (!mounted) return;
      setState(() {
        _game = data is Map ? Map<String, dynamic>.from(data) : const {};
        _detailLoading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadError = e.message;
        _detailLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadError = '${AppTranslations.t('app.network_error')}';
        _detailLoading = false;
      });
    }
  }

  Future<void> _launchGame() async {
    setState(() {
      _loading = true;
      _resultMsg = null;
    });

    try {
      final resp = await _api.post('/api/v1/game/launch', data: {'game_id': _game['id']});
      final data = resp['data'];
      setState(() {
        _resultMsg = data?['message'] ?? 'Game launched successfully';
        _resultSuccess = true;
        _loading = false;
      });
      // 后端返回游戏的启动地址（api_endpoint），直接在新窗口打开
      final launchUrlStr = data?['api_endpoint'] as String? ?? data?['url'] as String?;
      if (launchUrlStr != null && launchUrlStr.isNotEmpty && mounted) {
        await launchUrl(Uri.parse(launchUrlStr), mode: LaunchMode.externalApplication, webOnlyWindowName: '_blank');
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

  Widget _buildLoadError() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(_loadError!, style: const TextStyle(color: Colors.red)),
          const SizedBox(height: 12),
          if (_hashid != null)
            FilledButton.tonal(
              onPressed: () => _loadDetail(_hashid!),
              child: Text("${AppTranslations.t('app.retry')}"),
            ),
          const SizedBox(height: 8),
          // 深链进来时栈里没有上一页，这是页面唯一的出口
          OutlinedButton(
            onPressed: () => Get.offAllNamed('/games'),
            child: Text("${AppTranslations.t('game_detail.back_to_hall')}"),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final name = _game['name'] ?? 'Game';
    final description = _game['description'] ?? '';
    final type = _game['type'] ?? _game['game_type'] ?? '';
    final currencies = _game['currencies'];

    if (_loadError != null || _detailLoading) {
      return Scaffold(
        appBar: AppBar(
          title: Text(name),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => Navigator.canPop(context) ? Get.back() : Get.offAllNamed('/games'),
          ),
        ),
        body: Container(
          color: colorScheme.surfaceContainerLowest,
          child: _loadError != null ? _buildLoadError() : const Center(child: CircularProgressIndicator()),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(name),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          // 深链进来时栈里没有上一页，Get.back() 无处可退 ⇒ 退回大厅
          onPressed: () => Navigator.canPop(context) ? Get.back() : Get.offAllNamed('/games'),
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
