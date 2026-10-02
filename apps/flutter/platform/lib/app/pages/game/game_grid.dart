// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

// 游戏厅的卡片栅格 —— 从 `game_hall_page.dart` 里原样搬出来的（那页 571 行，超了 500 行纪律）。
//
// ⚠ 这是**搬**不是重写：判定逻辑（空态文案、平板 2 列/其余 4 列、卡片内的两次跳转）
// 与搬之前逐字相同。搬完 `game_hall_page.dart` 只剩「取数据 + 装配」。
//
// 只收三个入参而不是把整个 State 传进来：`games` 是父页已经过滤好的列表，
// `searchQuery` 只用来选空态那句文案（搜不到 ≠ 平台没游戏）。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../i18n/translations.dart';

class GameGrid extends StatelessWidget {
  const GameGrid({super.key, required this.games, required this.searchQuery});

  final List<Map<String, dynamic>> games;
  final String searchQuery;

  @override
  Widget build(BuildContext context) {
    if (games.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset('assets/mascot.png', width: 120),
            const SizedBox(height: 12),
            Text(
              searchQuery.isNotEmpty
                  ? '${AppTranslations.t('game_hall.no_results')}'
                  : '${AppTranslations.t('game_hall.no_games')}',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      );
    }

    final isTablet = ResponsiveBreakpoints.of(context).equals(TABLET);
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = isTablet ? 2 : 4;
        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1200),
            child: GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                mainAxisSpacing: 16,
                crossAxisSpacing: 16,
                childAspectRatio: 0.85,
              ),
              itemCount: games.length,
              itemBuilder: (context, index) => _GameCard(game: games[index]),
            ),
          ),
        );
      },
    );
  }
}

class _GameCard extends StatelessWidget {
  const _GameCard({required this.game});

  final Map<String, dynamic> game;

  /// 深链自救靠 URL 里的 id ⇒ 参数必须走 `parameters`（`Get.arguments` 只活在当次导航的内存里）。
  /// ⚠ 下面那句**必须留在单行**：`test/deep_link_rescue_test.dart` 会把全树里 `toNamed`
  /// 紧跟这个路由名的调用点当作「导航点」逐字扫出来 —— 注释里出现那串字面量会把它的括号
  /// 配平带跑（报「扫描器本身坏了」），而调用折行（`dart format` 在长度超 80 时会这么干）
  /// 又会让这个点从扫描器眼里消失。所以先用 `_params` 把它压到 80 列以内。
  Map<String, String> get _params => {'id': '${game['id']}'};

  void _open() {
    Get.toNamed('/game-detail', parameters: _params, arguments: game);
  }

  @override
  Widget build(BuildContext context) {
    final name = game['name'] ?? 'Unknown';
    final description = game['description'] ?? '';
    final type = game['type'] ?? game['game_type'] ?? '';
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: _open,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Cover image placeholder
            Expanded(
              flex: 3,
              child: Container(
                decoration: BoxDecoration(
                  color: colorScheme.primaryContainer.withValues(alpha: 0.3),
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(8),
                  ),
                ),
                child: Center(
                  child: Icon(
                    Icons.sports_esports,
                    size: 48,
                    color: colorScheme.primary.withValues(alpha: 0.5),
                  ),
                ),
              ),
            ),
            // Info area
            Expanded(
              flex: 2,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            name,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (type.isNotEmpty)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: colorScheme.secondaryContainer,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              type,
                              style: TextStyle(
                                fontSize: 11,
                                color: colorScheme.onSecondaryContainer,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Expanded(
                      child: Text(
                        description,
                        style: TextStyle(
                          fontSize: 12,
                          color: colorScheme.onSurfaceVariant,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 32,
                      child: FilledButton.icon(
                        onPressed: _open,
                        icon: const Icon(Icons.play_arrow, size: 18),
                        label: Text(
                          '${AppTranslations.t('game_hall.enter_game')}',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
