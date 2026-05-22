// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../profile/profile_page.dart';

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

  static const double sidebarWidth = 220;
  static const double sidebarCollapsedWidth = 64;
  static const double headerHeight = 56;

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);

  static const _navItems = [
    {'icon': Icons.sports_esports, 'label': '游戏大厅', 'route': '/games'},
    {'icon': Icons.account_balance_wallet, 'label': '我的钱包', 'route': '/wallet'},
    {'icon': Icons.person, 'label': '个人中心', 'route': '/profile'},
  ];

  @override
  void initState() {
    super.initState();
    _loadUsername();
    _fetchGames();
  }

  Future<void> _loadUsername() async {
    final name = await AuthService.getUsername();
    if (mounted) setState(() => _username = name ?? '用户');
  }

  Future<void> _fetchGames() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/game/list');
      final data = resp['data'];
      setState(() {
        _games = data is List ? List<Map<String, dynamic>>.from(data) : [];
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = '加载失败，请重试';
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
    final route = _navItems[index]['route'] as String;
    if (route == '/profile') {
      Get.to(() => const ProfilePage());
    } else {
      Get.toNamed(route);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isPhone) return _buildPhoneLayout();
    return _buildDesktopLayout();
  }

  // ─── Desktop / Tablet layout: sidebar + header + content ────────────

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
        title: const Text('游戏大厅'),
        actions: [_buildUserMenu()],
      ),
      drawer: Drawer(
        child: NavigationDrawer(
          selectedIndex: _selectedNav,
          onDestinationSelected: _onNavTap,
          children: [
            Container(
              height: headerHeight,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              alignment: Alignment.centerLeft,
              child: const Row(
                children: [
                  Icon(Icons.sports_esports, size: 24),
                  SizedBox(width: 8),
                  Text('游戏平台', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                ],
              ),
            ),
            const Divider(),
            ..._buildNavItems(),
          ],
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
      child: NavigationDrawer(
        selectedIndex: _selectedNav,
        onDestinationSelected: _onNavTap,
        children: [
          Container(
            height: headerHeight,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            alignment: Alignment.centerLeft,
            child: _sidebarCollapsed
                ? const Icon(Icons.sports_esports, size: 28)
                : const Row(
                    children: [
                      Icon(Icons.sports_esports, size: 24),
                      SizedBox(width: 8),
                      Text('游戏平台', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    ],
                  ),
          ),
          const Divider(),
          ..._buildNavItems(),
        ],
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
            tooltip: _sidebarCollapsed ? '展开菜单' : '收起菜单',
            onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed),
          ),
          const SizedBox(width: 16),
          const Text('游戏大厅', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
          const Spacer(),
          _buildUserMenu(),
        ],
      ),
    );
  }

  Widget _buildUserMenu() {
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
        if (value == 'profile') {
          Get.to(() => const ProfilePage());
        } else if (value == 'logout') {
          final confirm = await showDialog<bool>(
            context: context,
            builder: (ctx) => AlertDialog(
              title: const Text('确认退出'),
              content: const Text('确定要退出登录吗？'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('取消')),
                TextButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  child: const Text('确定退出', style: TextStyle(color: Colors.red)),
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
        const PopupMenuItem(value: 'profile', child: Text('个人中心')),
        const PopupMenuItem(value: 'logout', child: Text('退出登录')),
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
                  hintText: '搜索游戏...',
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
                              child: const Text('重试'),
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

  Widget _buildGameGrid() {
    final filtered = _filteredGames;
    if (filtered.isEmpty) {
      return Center(
        child: Text(
          _searchQuery.isNotEmpty ? '未找到匹配的游戏' : '暂无游戏',
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
                        label: const Text('进入游戏', style: TextStyle(fontSize: 12)),
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
