// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../services/auth_service.dart';
import '../pages/user/user_list_page.dart';
import '../pages/role/role_list_page.dart';
import '../pages/config/config_page.dart';
import '../pages/log/log_page.dart';
import '../pages/dashboard/dashboard_page.dart';
import '../pages/profile/profile_page.dart';
import '../pages/game/game_list_page.dart';
import '../pages/withdraw/withdraw_page.dart';
import '../pages/platform_user/platform_user_page.dart';
import '../pages/payment/payment_page.dart';
import '../pages/announcement/announcement_page.dart';

class AdminLayout extends StatefulWidget {
  final Widget child;
  final int initialIndex;
  const AdminLayout({super.key, required this.child, this.initialIndex = 0});

  @override
  State<AdminLayout> createState() => _AdminLayoutState();
}

class _AdminLayoutState extends State<AdminLayout> {
  late int _selectedIndex = widget.initialIndex;
  late Widget _currentChild;
  bool _sidebarCollapsed = false;
  String? _previousBreakpoint;
  static const double sidebarWidth = 240;
  static const double sidebarCollapsedWidth = 64;
  static const double headerHeight = 56;

  static const _pages = <Widget>[
    DashboardPage(),
    UserListPage(),
    RoleListPage(),
    ConfigPage(),
    LogPage(),
    GameListPage(),
    WithdrawPage(),
    PlatformUserPage(),
    PaymentPage(),
    AnnouncementPage(),
  ];

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);
  bool get _isTablet => _bp.equals(TABLET);

  @override
  void initState() {
    super.initState();
    _currentChild = _pages[_selectedIndex];
    _checkAuth();
  }

  void _checkAuth() async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn && mounted) {
      Navigator.of(context).pushReplacementNamed('/login');
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final current = _bp.breakpoint.name;
    if (_previousBreakpoint != null && _previousBreakpoint != current) {
      _sidebarCollapsed = _isTablet;
    }
    _previousBreakpoint = current;
  }

  void _onNavChanged(int index) {
    setState(() {
      _selectedIndex = index;
      _currentChild = _pages[index.clamp(0, _pages.length - 1)];
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isPhone) return _buildPhoneLayout();
    return _buildDesktopLayout();
  }

  // ─── PHONE layout: AppBar + Drawer ────────────────────────────────

  Widget _buildPhoneLayout() {
    return Scaffold(
      appBar: AppBar(
        title: const Text('管理后台'),
        actions: [_buildUserMenu()],
      ),
      drawer: Drawer(
        child: NavigationDrawer(
          selectedIndex: _selectedIndex,
          onDestinationSelected: _onNavChanged,
          children: [
            Container(
              height: headerHeight,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              alignment: Alignment.centerLeft,
              child: const Row(
                children: [
                  Icon(Icons.admin_panel_settings, size: 24),
                  SizedBox(width: 8),
                  Text('管理后台',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                ],
              ),
            ),
            const Divider(),
            ..._buildNavItems(),
          ],
        ),
      ),
      body: Container(
        color: Theme.of(context).colorScheme.surfaceContainerLowest,
        padding: const EdgeInsets.all(16),
        child: _currentChild,
      ),
    );
  }

  // ─── DESKTOP / TABLET layout: sidebar + header + content ───────────

  Widget _buildDesktopLayout() {
    return Scaffold(
      body: Row(
        children: [
          _buildSidebar(),
          Expanded(
            child: Column(
              children: [
                _buildHeader(),
                Expanded(
                  child: Container(
                    color: Theme.of(context).colorScheme.surfaceContainerLowest,
                    padding: const EdgeInsets.all(16),
                    child: _currentChild,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSidebar() {
    final width = _sidebarCollapsed ? sidebarCollapsedWidth : sidebarWidth;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: width,
      child: NavigationDrawer(
        selectedIndex: _selectedIndex,
        onDestinationSelected: _onNavChanged,
        children: [
          Container(
            height: headerHeight,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            alignment: Alignment.centerLeft,
            child: _sidebarCollapsed
                ? const Icon(Icons.admin_panel_settings, size: 28)
                : const Row(
                    children: [
                      Icon(Icons.admin_panel_settings, size: 24),
                      SizedBox(width: 8),
                      Text('管理后台',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
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
    return const [
      NavigationDrawerDestination(
        icon: Icon(Icons.dashboard, size: 20),
        label: Text('仪表盘'),
        selectedIcon: Icon(Icons.dashboard, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.people, size: 20),
        label: Text('用户管理'),
        selectedIcon: Icon(Icons.people, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.security, size: 20),
        label: Text('角色权限'),
        selectedIcon: Icon(Icons.security, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.settings, size: 20),
        label: Text('系统配置'),
        selectedIcon: Icon(Icons.settings, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.description, size: 20),
        label: Text('操作日志'),
        selectedIcon: Icon(Icons.description, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.games, size: 20),
        label: Text('游戏管理'),
        selectedIcon: Icon(Icons.games, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.account_balance_wallet, size: 20),
        label: Text('提现管理'),
        selectedIcon: Icon(Icons.account_balance_wallet, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.group, size: 20),
        label: Text('平台用户'),
        selectedIcon: Icon(Icons.group, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.payment, size: 20),
        label: Text('支付管理'),
        selectedIcon: Icon(Icons.payment, size: 20),
      ),
      NavigationDrawerDestination(
        icon: Icon(Icons.campaign, size: 20),
        label: Text('公告管理'),
        selectedIcon: Icon(Icons.campaign, size: 20),
      ),
    ];
  }

  Widget _buildHeader() {
    return Container(
      height: headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(
          bottom: BorderSide(color: Theme.of(context).dividerColor),
        ),
      ),
      child: Row(
        children: [
          IconButton(
            icon: Icon(_sidebarCollapsed ? Icons.menu_open : Icons.menu),
            tooltip: _sidebarCollapsed ? '展开菜单' : '收起菜单',
            onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed),
          ),
          const Spacer(),
          _buildUserMenu(),
        ],
      ),
    );
  }

  Widget _buildUserMenu() {
    return PopupMenuButton<String>(
      offset: const Offset(0, headerHeight),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)),
          SizedBox(width: 8),
          Text('管理员', style: TextStyle(fontSize: 14)),
          Icon(Icons.arrow_drop_down, size: 20),
        ],
      ),
      onSelected: (value) {
        if (value == 'profile') {
          Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfilePage()));
        } else if (value == 'logout') {
          showDialog(
            context: context,
            builder: (ctx) => AlertDialog(
              title: const Text('确认退出'),
              content: const Text('确定要退出登录吗？'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
                TextButton(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    await AuthService.clearToken();
                    Navigator.of(context).pushReplacementNamed('/login');
                  },
                  child: const Text('确定退出', style: TextStyle(color: Colors.red)),
                ),
              ],
            ),
          );
        }
      },
      itemBuilder: (_) => [
        const PopupMenuItem(value: 'profile', child: Text('个人中心')),
        const PopupMenuItem(value: 'logout', child: Text('退出登录')),
      ],
    );
  }
}
