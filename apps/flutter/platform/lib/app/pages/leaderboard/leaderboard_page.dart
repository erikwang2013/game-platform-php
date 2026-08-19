// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_helpers.dart';
import '../../services/api_service.dart';

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
      final resp = await _api.get('/api/leaderboard/list');
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
    });
    try {
      final resp = await _api.get('/api/leaderboard/$id');
      final data = resp['data'];
      final ranks = data is Map ? (data['ranking'] ?? data['rankings']) : data;
      setState(() {
        _ranking = ApiHelpers.extractList(ranks is List ? ranks : data);
        _rankingLoading = false;
      });
    } catch (_) {
      setState(() {
        _ranking = [];
        _rankingLoading = false;
      });
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
                  ? Center(child: Text('${AppTranslations.t('leaderboard.empty')}'))
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
                        Expanded(
                          child: _rankingLoading
                              ? const Center(child: CircularProgressIndicator())
                              : _ranking.isEmpty
                                  ? Center(child: Text('${AppTranslations.t('leaderboard.empty')}'))
                                  : ListView.builder(
                                      itemCount: _ranking.length,
                                      itemBuilder: (_, i) {
                                        final row = _ranking[i];
                                        return ListTile(
                                          leading: CircleAvatar(child: Text('${row['rank'] ?? i + 1}')),
                                          title: Text('${row['username'] ?? row['user_id'] ?? '-'}'),
                                          trailing: Text('${row['score'] ?? ''}'),
                                        );
                                      },
                                    ),
                        ),
                      ],
                    ),
    );
  }
}
