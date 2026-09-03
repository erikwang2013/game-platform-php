// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../i18n/translations.dart';
import '../../i18n/locale_controller.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/api_helpers.dart';
import '../../services/chat_service.dart';

class GameHallPage extends StatefulWidget {
  const GameHallPage({super.key});

  @override
  State<GameHallPage> createState() => _GameHallPageState();
}

class _GameHallPageState extends State<GameHallPage> {
  final _api = ApiService();
  List<Map<String, dynamic>> _games = [];
  bool _loading = true;
  String? _error;
  String _searchQuery = '';
  String _username = '';
  int _selectedNav = 0;
  bool _sidebarCollapsed = false;
  Map<String, dynamic> _stats = {};
  bool _statsVisible = false;

  static const double sidebarWidth = 220;
  static const double sidebarCollapsedWidth = 64;
  static const double headerHeight = 56;

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);

  List<Map<String, dynamic>> get _navItems => [
    {'icon': Icons.sports_esports, 'label': '${AppTranslations.t('nav.games')}', 'route': '/games'},
    {'icon': Icons.account_balance_wallet, 'label': '${AppTranslations.t('nav.wallet')}', 'route': '/wallet'},
    {'icon': Icons.local_offer_outlined, 'label': '${AppTranslations.t('nav.coupons')}', 'route': '/coupons'},
    {'icon': Icons.leaderboard_outlined, 'label': '${AppTranslations.t('nav.leaderboard')}', 'route': '/leaderboard'},
    {'icon': Icons.notifications_outlined, 'label': '${AppTranslations.t('nav.notifications')}', 'route': '/notifications'},
    {'icon': Icons.chat_bubble_outline, 'label': '${AppTranslations.t('nav.chat')}', 'route': '/chat-list'},
    {'icon': Icons.people_outline, 'label': '${AppTranslations.t('nav.friends')}', 'route': '/friends'},
    {'icon': Icons.verified_user, 'label': '${AppTranslations.t('nav.identity')}', 'route': '/identity'},
    {'icon': Icons.history, 'label': '${AppTranslations.t('nav.play_history')}', 'route': '/play-logs'},
    {'icon': Icons.person, 'label': '${AppTranslations.t('nav.profile')}', 'route': '/profile'},
  ];

  @override
  void initState() {
    super.initState();
    _loadUsername();
    _fetchGames();
    _fetchStats();
    // 登录成功/会话恢复后统一在这里建立聊天连接（connect 内部无 token 时为 no-op）
    Get.find<ChatService>().connect();
  }

  Future<void> _fetchStats() async {
    try {
      final resp = await _api.get('/api/v1/platform/stats');
      if (!mounted) return;
      final data = resp['data'];
      setState(() {
        _stats = data is Map<String, dynamic> ? data : {};
        _statsVisible = _stats.isNotEmpty;
      });
    } catch (_) {
      // 统计失败时隐藏横幅，不阻塞游戏列表
    }
  }

  Future<void> _loadUsername() async {
    final name = await AuthService.getUsername();
    if (mounted) setState(() => _username = name ?? 'User');
  }

  Future<void> _fetchGames() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/v1/game/list');
      setState(() {
        _games = ApiHelpers.extractList(resp['data']);
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = '${AppTranslations.t('app.loading_failed')}';
        _loading = false;
      });
    }
  }

  List<Map<String, dynamic>> get _filteredGames {
    if (_searchQuery.isEmpty) return _games;
    final query = _searchQuery.toLowerCase();
    return _games.where((g) {
      final name = (g['name'] ?? '').toString().toLowerCase();
      final desc = (g['description'] ?? '').toString().toLowerCase();
      return name.contains(query) || desc.contains(query);
    }).toList();
  }

  void _onNavTap(int index) {
    setState(() => _selectedNav = index);
    final navItems = _navItems;
    final route = navItems[index]['route'] as String;
    if (route == '/games') return;
    Get.toNamed(route);
  }

  @override
  Widget build(BuildContext context) {
    if (_isPhone) return _buildPhoneLayout();
    return _buildDesktopLayout();
  }

  // --- Desktop / Tablet layout: sidebar + header + content ---

  Widget _buildDesktopLayout() {
    return Scaffold(
      body: Row(
        children: [
          _buildSidebar(),
          Expanded(
            child: Column(
              children: [
                _buildHeader(),
                Expanded(child: _buildContent()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPhoneLayout() {
    return Scaffold(
      appBar: AppBar(
        title: GetBuilder<LocaleController>(
          builder: (_) => Text('${AppTranslations.t('game_hall.title')}'),
        ),
        actions: [_buildUserMenu()],
      ),
      drawer: Drawer(
        child: GetBuilder<LocaleController>(
          builder: (_) => NavigationDrawer(
            selectedIndex: _selectedNav,
            onDestinationSelected: _onNavTap,
            children: [
              Container(
                height: headerHeight,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                alignment: Alignment.centerLeft,
                child: Row(
                  children: [
                    const Icon(Icons.sports_esports, size: 24),
                    const SizedBox(width: 8),
                    Text('${AppTranslations.t('common.platform')}',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
              const Divider(),
              ..._buildNavItems(),
            ],
          ),
        ),
      ),
      body: _buildContent(),
    );
  }

  Widget _buildSidebar() {
    final width = _sidebarCollapsed ? sidebarCollapsedWidth : sidebarWidth;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: width,
      child: GetBuilder<LocaleController>(
        builder: (_) => NavigationDrawer(
          selectedIndex: _selectedNav,
          onDestinationSelected: _onNavTap,
          children: [
            Container(
              height: headerHeight,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              alignment: Alignment.centerLeft,
              child: _sidebarCollapsed
                  ? const Icon(Icons.sports_esports, size: 28)
                  : Row(
                      children: [
                        const Icon(Icons.sports_esports, size: 24),
                        const SizedBox(width: 8),
                        Text('${AppTranslations.t('common.platform')}',
                            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      ],
                    ),
            ),
            const Divider(),
            ..._buildNavItems(),
          ],
        ),
      ),
    );
  }

  List<NavigationDrawerDestination> _buildNavItems() {
    return _navItems.map((item) {
      return NavigationDrawerDestination(
        icon: Icon(item['icon'] as IconData, size: 20),
        label: Text(item['label'] as String),
        selectedIcon: Icon(item['icon'] as IconData, size: 20),
      );
    }).toList();
  }

  Widget _buildHeader() {
    return Container(
      height: headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(bottom: BorderSide(color: Theme.of(context).dividerColor)),
      ),
      child: Row(
        children: [
          IconButton(
            icon: Icon(_sidebarCollapsed ? Icons.menu_open : Icons.menu),
            tooltip: _sidebarCollapsed
                ? '${AppTranslations.t('common.expand_menu')}'
                : '${AppTranslations.t('common.collapse_menu')}',
            onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed),
          ),
          const SizedBox(width: 16),
          GetBuilder<LocaleController>(
            builder: (_) => Text('${AppTranslations.t('game_hall.title')}',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
          ),
          const Spacer(),
          _buildUserMenu(),
        ],
      ),
    );
  }

  Widget _buildUserMenu() {
    final localeCtrl = Get.find<LocaleController>();
    return PopupMenuButton<String>(
      offset: const Offset(0, headerHeight),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)),
          const SizedBox(width: 8),
          Text(_username, style: const TextStyle(fontSize: 14)),
          const Icon(Icons.arrow_drop_down, size: 20),
        ],
      ),
      onSelected: (value) async {
        if (value == 'lang') {
          final current = localeCtrl.currentLocale.value;
          localeCtrl.changeLocale(current == 'zh' ? 'en' : 'zh');
        } else if (value == 'profile') {
          Get.toNamed('/profile');
        } else if (value == 'logout') {
          final confirm = await showDialog<bool>(
            context: context,
            builder: (ctx) => AlertDialog(
              title: Text('${AppTranslations.t('app.confirm_logout')}'),
              content: Text('${AppTranslations.t('app.confirm_logout')}'),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: Text('${AppTranslations.t('app.cancel')}'),
                ),
                TextButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  child: Text('${AppTranslations.t('app.confirm')}',
                      style: const TextStyle(color: Colors.red)),
                ),
              ],
            ),
          );
          if (confirm == true) {
            await AuthService.clearToken();
            Get.offAllNamed('/login');
          }
        }
      },
      itemBuilder: (_) => [
        PopupMenuItem(
          value: 'lang',
          child: Obx(() {
            final isZh = localeCtrl.currentLocale.value == 'zh';
            return Row(
              children: [
                const Icon(Icons.language, size: 18),
                const SizedBox(width: 8),
                Text(isZh ? 'Switch to English' : '切换到中文'),
              ],
            );
          }),
        ),
        PopupMenuItem(
          value: 'profile',
          child: Text('${AppTranslations.t('profile.title')}'),
        ),
        PopupMenuItem(
          value: 'logout',
          child: Text('${AppTranslations.t('profile.logout')}'),
        ),
      ],
    );
  }

  Widget _buildContent() {
    return Container(
      color: Theme.of(context).colorScheme.surfaceContainerLowest,
      child: Column(
        children: [
          // Search bar
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1200),
              child: TextField(
                decoration: InputDecoration(
                  hintText: '${AppTranslations.t('game_hall.search_hint')}',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () => setState(() => _searchQuery = ''),
                        )
                      : null,
                  border: const OutlineInputBorder(),
                ),
                onChanged: (v) => setState(() => _searchQuery = v),
              ),
            ),
          ),
          const SizedBox(height: 8),

          if (_statsVisible) ...[
            _buildStatsBanner(),
            const SizedBox(height: 8),
          ],

          // Game grid
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, style: const TextStyle(color: Colors.red)),
                            const SizedBox(height: 16),
                            FilledButton.tonal(
                              onPressed: _fetchGames,
                              child: Text('${AppTranslations.t('app.retry')}'),
                            ),
                          ],
                        ),
                      )
                    : _buildGameGrid(),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBanner() {
    final items = <(String, String, IconData, Color)>[
      ('${AppTranslations.t('stats.total_games')}', _stats['total_games']?.toString() ?? '0', Icons.sports_esports, const Color(0xFF1677FF)),
      ('${AppTranslations.t('stats.total_users')}', _stats['total_users']?.toString() ?? '0', Icons.people, const Color(0xFF52C41A)),
      ('${AppTranslations.t('stats.today_plays')}', _stats['today_game_plays']?.toString() ?? '0', Icons.play_circle, const Color(0xFFFA8C16)),
      ('${AppTranslations.t('stats.active_7d')}', _stats['active_users_7d']?.toString() ?? '0', Icons.trending_up, const Color(0xFF722ED1)),
    ];
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1200),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final crossAxisCount = constraints.maxWidth > 900 ? 4 : 2;
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                mainAxisExtent: 84,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemCount: items.length,
              itemBuilder: (context, index) {
                final (label, value, icon, color) = items[index];
                return Card(
                  color: color.withValues(alpha: 0.08),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Icon(icon, color: color, size: 28),
                        const SizedBox(width: 12),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(label, style: TextStyle(fontSize: 12, color: color)),
                            const SizedBox(height: 4),
                            Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }

  Widget _buildGameGrid() {
    final filtered = _filteredGames;
    if (filtered.isEmpty) {
      return Center(
        child: Text(
          _searchQuery.isNotEmpty
              ? '${AppTranslations.t('game_hall.no_results')}'
              : '${AppTranslations.t('game_hall.no_games')}',
          style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
        ),
      );
    }

    final isTablet = _bp.equals(TABLET);
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
              itemCount: filtered.length,
              itemBuilder: (context, index) => _buildGameCard(filtered[index]),
            ),
          ),
        );
      },
    );
  }

  Widget _buildGameCard(Map<String, dynamic> game) {
    final name = game['name'] ?? 'Unknown';
    final description = game['description'] ?? '';
    final type = game['type'] ?? game['game_type'] ?? '';
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () => Get.toNamed('/game-detail', arguments: game),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Cover image placeholder
            Expanded(
              flex: 3,
              child: Container(
                decoration: BoxDecoration(
                  color: colorScheme.primaryContainer.withValues(alpha: 0.3),
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(8)),
                ),
                child: Center(
                  child: Icon(Icons.sports_esports, size: 48, color: colorScheme.primary.withValues(alpha: 0.5)),
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
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (type.isNotEmpty)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: colorScheme.secondaryContainer,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(type, style: TextStyle(fontSize: 11, color: colorScheme.onSecondaryContainer)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Expanded(
                      child: Text(
                        description,
                        style: TextStyle(fontSize: 12, color: colorScheme.onSurfaceVariant),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 32,
                      child: FilledButton.icon(
                        onPressed: () => Get.toNamed('/game-detail', arguments: game),
                        icon: const Icon(Icons.play_arrow, size: 18),
                        label: Text('${AppTranslations.t('game_hall.enter_game')}',
                            style: const TextStyle(fontSize: 12)),
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
