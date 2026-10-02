// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_helpers.dart';
import '../../services/api_service.dart';

/// 榜单区域的四态：加载中 / 取数失败（可重试）/ 空（真没人上榜）/ 有数据。
/// 旧代码把失败静默折成空列表，页面把「拉取失败」渲染成「暂无排名」，
/// 用户看到的是数据结论，实际是请求失败。
enum RankingView { loading, failed, empty, data }

RankingView rankingViewOf({required bool loading, required String? error, required int count}) {
  if (loading) return RankingView.loading;
  if (error != null) return RankingView.failed;
  return count == 0 ? RankingView.empty : RankingView.data;
}

/// 身份列只展示末 4 个字符（与两棵 web 树的 `maskedId` 同形）。
///
/// 榜单条目只有 rank / user_id / score —— **没有昵称**，所以这一列先天只能显示编号；
/// 而 `user_id` 自 2026-10-02 起是 **hashid 字符串**（服务端出网前逐行 `encodeId`，
/// 此前是裸数据库整数）。末 4 位是字母数字混排，取的是**字符**不是数字位。
String maskUserId(dynamic raw) {
  final s = '${raw ?? ''}';
  if (s.isEmpty) return '-';
  return '#···${s.length <= 4 ? s : s.substring(s.length - 4)}';
}

class LeaderboardPage extends StatefulWidget {
  const LeaderboardPage({super.key});

  @override
  State<LeaderboardPage> createState() => _LeaderboardPageState();
}

class _LeaderboardPageState extends State<LeaderboardPage> {
  final _api = ApiService();
  List<Map<String, dynamic>> _boards = [];
  List<Map<String, dynamic>> _ranking = [];
  String? _selectedId;
  String? _selectedName;
  bool _loading = true;
  bool _rankingLoading = false;
  String? _error;
  /// 榜单取数失败的原因：与「真没人上榜」分开（失败要能重试）
  String? _rankingError;

  @override
  void initState() {
    super.initState();
    _loadBoards();
  }

  Future<void> _loadBoards() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/v1/leaderboard/list');
      final boards = ApiHelpers.extractList(resp['data']);
      setState(() {
        _boards = boards;
        _loading = false;
      });
      if (boards.isNotEmpty) {
        await _loadRanking('${boards.first['id']}', '${boards.first['name'] ?? ''}');
      }
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

  Future<void> _loadRanking(String id, String name) async {
    setState(() {
      _selectedId = id;
      _selectedName = name;
      _rankingLoading = true;
      _rankingError = null;
    });
    try {
      final resp = await _api.get('/api/v1/leaderboard/$id');
      final data = resp['data'];
      final ranks = data is Map ? (data['ranking'] ?? data['rankings']) : data;
      setState(() {
        _ranking = ApiHelpers.extractList(ranks is List ? ranks : data);
        _rankingLoading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _ranking = [];
        _rankingError = e.message;
        _rankingLoading = false;
      });
    } catch (_) {
      setState(() {
        _ranking = [];
        _rankingError = '${AppTranslations.t('app.network_error')}';
        _rankingLoading = false;
      });
    }
  }

  Widget _buildRanking() {
    switch (rankingViewOf(loading: _rankingLoading, error: _rankingError, count: _ranking.length)) {
      case RankingView.loading:
        return const Center(child: CircularProgressIndicator());
      case RankingView.failed:
        return Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_rankingError!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: _selectedId == null ? null : () => _loadRanking(_selectedId!, _selectedName ?? ''),
                child: Text('${AppTranslations.t('app.retry')}'),
              ),
            ],
          ),
        );
      case RankingView.empty:
        return Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Image.asset('assets/mascot.png', width: 120),
              const SizedBox(height: 12),
              Text('${AppTranslations.t('leaderboard.empty')}'),
            ],
          ),
        );
      case RankingView.data:
        return ListView.builder(
          itemCount: _ranking.length,
          itemBuilder: (_, i) {
            final row = _ranking[i];
            return ListTile(
              leading: CircleAvatar(child: Text('${row['rank'] ?? i + 1}')),
              // 原先这里是 `row['username'] ?? row['user_id']`：`username` 后端从来不下发
              // （`LeaderboardController::ranking` 只拼 rank/user_id/score），是条死分支。
              title: Text(maskUserId(row['user_id'])),
              trailing: Text('${row['score'] ?? ''}'),
            );
          },
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${AppTranslations.t('leaderboard.title')}'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 12),
                      FilledButton.tonal(onPressed: _loadBoards, child: Text('${AppTranslations.t('app.retry')}')),
                    ],
                  ),
                )
              : _boards.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Image.asset('assets/mascot.png', width: 120),
                          const SizedBox(height: 12),
                          Text('${AppTranslations.t('leaderboard.empty')}'),
                        ],
                      ),
                    )
                  : Column(
                      children: [
                        SizedBox(
                          height: 52,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: _boards.length,
                            separatorBuilder: (_, __) => const SizedBox(width: 8),
                            itemBuilder: (_, i) {
                              final board = _boards[i];
                              final id = '${board['id']}';
                              final selected = id == _selectedId;
                              return ChoiceChip(
                                label: Text('${board['name'] ?? id}'),
                                selected: selected,
                                onSelected: (_) => _loadRanking(id, '${board['name'] ?? ''}'),
                              );
                            },
                          ),
                        ),
                        if (_selectedName != null && _selectedName!.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                            child: Align(
                              alignment: Alignment.centerLeft,
                              child: Text(_selectedName!, style: const TextStyle(fontWeight: FontWeight.w600)),
                            ),
                          ),
                        Expanded(child: _buildRanking()),
                      ],
                    ),
    );
  }
}
