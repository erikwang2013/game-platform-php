// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../services/auth_service.dart';
import '../i18n/translations.dart';
import '../i18n/locale_controller.dart';
import '../pages/user/user_list_page.dart';
import '../pages/role/role_list_page.dart';
import '../pages/config/config_page.dart';
import '../pages/log/log_page.dart';
import '../pages/dashboard/dashboard_page.dart';
import '../pages/report/report_page.dart';
import '../pages/profile/profile_page.dart';
import '../pages/game/game_list_page.dart';
import '../pages/withdraw/withdraw_page.dart';
import '../pages/platform_user/platform_user_page.dart';
import '../pages/identity/identity_page.dart';
import '../pages/risk/risk_log_page.dart';
import '../pages/payment/payment_page.dart';
import '../pages/cdn/cdn_page.dart';
import '../pages/announcement/announcement_page.dart';
import '../pages/vip/vip_page.dart';
import '../pages/achievement/achievement_page.dart';
import '../pages/activity/activity_page.dart';
import '../pages/risk/risk_dashboard_page.dart';

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
    ReportPage(),
    UserListPage(),
    RoleListPage(),
    ConfigPage(),
    LogPage(),
    GameListPage(),
    WithdrawPage(),
    PlatformUserPage(),
    IdentityPage(),
    RiskLogPage(),
    RiskDashboardPage(),
    PaymentPage(),
    CdnPage(),
    AnnouncementPage(),
    VipPage(),
    AchievementPage(),
    ActivityPage(),
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

  // ─── 命令面板 + 全局快捷键 ────────────────────────────────────────
  // 列表与 _buildNavItems() 顺序一致：key 为翻译键，'!' 开头为硬编码中文
  static const _paletteItems = <(String, IconData, int)>[
    ('nav.dashboard', Icons.dashboard, 0),
    ('nav.reports', Icons.bar_chart, 1),
    ('nav.users', Icons.people, 2),
    ('nav.roles', Icons.security, 3),
    ('nav.config', Icons.settings, 4),
    ('nav.logs', Icons.description, 5),
    ('nav.games', Icons.games, 6),
    ('nav.withdraws', Icons.account_balance_wallet, 7),
    ('nav.platform_users', Icons.group, 8),
    ('nav.identity', Icons.verified_user, 9),
    ('nav.risk_logs', Icons.warning, 10),
    ('!风控大盘', Icons.monitor_heart, 11),
    ('nav.payments', Icons.payment, 12),
    ('nav.cdn', Icons.cloud, 13),
    ('nav.announcements', Icons.campaign, 14),
    ('nav.vip', Icons.workspace_premium, 15),
    ('nav.achievements', Icons.emoji_events, 16),
    ('!运营活动', Icons.local_activity, 17),
  ];

  @override
  Widget build(BuildContext context) {
    return Focus(
      autofocus: true,
      onKeyEvent: _handleKey,
      child: _isPhone ? _buildPhoneLayout() : _buildDesktopLayout(),
    );
  }

  KeyEventResult _handleKey(FocusNode node, KeyEvent event) {
    if (event is! KeyDownEvent) return KeyEventResult.ignored;
    final key = event.logicalKey;
    final ctrl = HardwareKeyboard.instance.isControlPressed || HardwareKeyboard.instance.isMetaPressed;
    if (ctrl && key == LogicalKeyboardKey.keyK) {
      _openCommandPalette();
      return KeyEventResult.handled;
    }
    if (HardwareKeyboard.instance.isAltPressed) {
      if (key >= LogicalKeyboardKey.digit1 && key <= LogicalKeyboardKey.digit9) {
        _onNavChanged(key.keyId - LogicalKeyboardKey.digit1.keyId);
        return KeyEventResult.handled;
      }
      if (key == LogicalKeyboardKey.keyL) {
        _onNavChanged(5);
        return KeyEventResult.handled;
      }
      if (key == LogicalKeyboardKey.keyU) {
        _onNavChanged(2);
        return KeyEventResult.handled;
      }
    }
    return KeyEventResult.ignored;
  }

  void _openCommandPalette() {
    showDialog<void>(
      context: context,
      builder: (_) => _CommandPalette(
        items: _paletteItems,
        onSelected: (index) {
          Navigator.of(context).pop();
          _onNavChanged(index);
        },
      ),
    );
  }

  // ─── PHONE layout: AppBar + Drawer ────────────────────────────────

  Widget _buildPhoneLayout() {
    return Scaffold(
      appBar: AppBar(
        title: Obx(() => Text("${AppTranslations.t('common.admin_panel')}")),
        actions: [_buildUserMenu()],
      ),
      drawer: Drawer(
        child: GetBuilder<LocaleController>(
          builder: (lc) => NavigationDrawer(
            selectedIndex: _selectedIndex,
            onDestinationSelected: _onNavChanged,
            children: [
              Container(
                height: headerHeight,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                alignment: Alignment.centerLeft,
                child: Row(
                  children: [
                    const Icon(Icons.admin_panel_settings, size: 24),
                    const SizedBox(width: 8),
                    Text("${AppTranslations.t('common.admin_panel')}",
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
      child: GetBuilder<LocaleController>(
        builder: (lc) => NavigationDrawer(
          selectedIndex: _selectedIndex,
          onDestinationSelected: _onNavChanged,
          children: [
            Container(
              height: headerHeight,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              alignment: Alignment.centerLeft,
              child: _sidebarCollapsed
                  ? const Icon(Icons.admin_panel_settings, size: 28)
                  : Row(
                      children: [
                        const Icon(Icons.admin_panel_settings, size: 24),
                        const SizedBox(width: 8),
                        Text("${AppTranslations.t('common.admin_panel')}",
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
    return [
      NavigationDrawerDestination(
        icon: const Icon(Icons.dashboard, size: 20),
        label: Text("${AppTranslations.t('nav.dashboard')}"),
        selectedIcon: const Icon(Icons.dashboard, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.bar_chart, size: 20),
        label: Text("${AppTranslations.t('nav.reports')}"),
        selectedIcon: const Icon(Icons.bar_chart, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.people, size: 20),
        label: Text("${AppTranslations.t('nav.users')}"),
        selectedIcon: const Icon(Icons.people, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.security, size: 20),
        label: Text("${AppTranslations.t('nav.roles')}"),
        selectedIcon: const Icon(Icons.security, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.settings, size: 20),
        label: Text("${AppTranslations.t('nav.config')}"),
        selectedIcon: const Icon(Icons.settings, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.description, size: 20),
        label: Text("${AppTranslations.t('nav.logs')}"),
        selectedIcon: const Icon(Icons.description, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.games, size: 20),
        label: Text("${AppTranslations.t('nav.games')}"),
        selectedIcon: const Icon(Icons.games, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.account_balance_wallet, size: 20),
        label: Text("${AppTranslations.t('nav.withdraws')}"),
        selectedIcon: const Icon(Icons.account_balance_wallet, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.group, size: 20),
        label: Text("${AppTranslations.t('nav.platform_users')}"),
        selectedIcon: const Icon(Icons.group, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.verified_user, size: 20),
        label: Text("${AppTranslations.t('nav.identity')}"),
        selectedIcon: const Icon(Icons.verified_user, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.warning, size: 20),
        label: Text("${AppTranslations.t('nav.risk_logs')}"),
        selectedIcon: const Icon(Icons.warning, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.monitor_heart, size: 20),
        label: const Text('风控大盘'),
        selectedIcon: const Icon(Icons.monitor_heart, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.payment, size: 20),
        label: Text("${AppTranslations.t('nav.payments')}"),
        selectedIcon: const Icon(Icons.payment, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.cloud, size: 20),
        label: Text("${AppTranslations.t('nav.cdn')}"),
        selectedIcon: const Icon(Icons.cloud, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.campaign, size: 20),
        label: Text("${AppTranslations.t('nav.announcements')}"),
        selectedIcon: const Icon(Icons.campaign, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.workspace_premium, size: 20),
        label: Text("${AppTranslations.t('nav.vip')}"),
        selectedIcon: const Icon(Icons.workspace_premium, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.emoji_events, size: 20),
        label: Text("${AppTranslations.t('nav.achievements')}"),
        selectedIcon: const Icon(Icons.emoji_events, size: 20),
      ),
      NavigationDrawerDestination(
        icon: const Icon(Icons.local_activity, size: 20),
        label: const Text('运营活动'),
        selectedIcon: const Icon(Icons.local_activity, size: 20),
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
            tooltip: _sidebarCollapsed
                ? "${AppTranslations.t('common.expand_menu')}"
                : "${AppTranslations.t('common.collapse_menu')}",
            onPressed: () => setState(() => _sidebarCollapsed = !_sidebarCollapsed),
          ),
          const Spacer(),
          IconButton(
            icon: const Icon(Icons.monitor),
            tooltip: '${AppTranslations.t('bigscreen.enter')}',
            onPressed: () => Navigator.of(context).pushNamed('/bigscreen'),
          ),
          _buildUserMenu(),
        ],
      ),
    );
  }

  Widget _buildUserMenu() {
    final localeCtrl = Get.find<LocaleController>();
    return PopupMenuButton<String>(
      offset: const Offset(0, headerHeight),
      child: Obx(() {
        final isZh = localeCtrl.currentLocale.value == 'zh';
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircleAvatar(radius: 14, child: Icon(Icons.person, size: 16)),
            const SizedBox(width: 8),
            Text(isZh ? '管理员' : 'Admin', style: const TextStyle(fontSize: 14)),
            const Icon(Icons.arrow_drop_down, size: 20),
          ],
        );
      }),
      onSelected: (value) {
        if (value == 'lang') {
          final current = localeCtrl.currentLocale.value;
          localeCtrl.changeLocale(current == 'zh' ? 'en' : 'zh');
        } else if (value == 'profile') {
          Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfilePage()));
        } else if (value == 'logout') {
          showDialog(
            context: context,
            builder: (ctx) => AlertDialog(
              title: Text("${AppTranslations.t('app.confirm_logout')}"),
              content: Text("${AppTranslations.t('app.confirm_logout')}"),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: Text("${AppTranslations.t('app.cancel')}"),
                ),
                TextButton(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    await AuthService.clearToken();
                    Navigator.of(context).pushReplacementNamed('/login');
                  },
                  child: Text("${AppTranslations.t('app.confirm')}",
                      style: const TextStyle(color: Colors.red)),
                ),
              ],
            ),
          );
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
          child: Text("${AppTranslations.t('profile.title')}"),
        ),
        PopupMenuItem(
          value: 'logout',
          child: Text("${AppTranslations.t('app.logout')}"),
        ),
      ],
    );
  }
}

/// Ctrl+K 命令面板：输入即过滤，回车或点击跳转页面
class _CommandPalette extends StatefulWidget {
  final List<(String, IconData, int)> items;
  final ValueChanged<int> onSelected;
  const _CommandPalette({required this.items, required this.onSelected});

  @override
  State<_CommandPalette> createState() => _CommandPaletteState();
}

class _CommandPaletteState extends State<_CommandPalette> {
  final _query = TextEditingController();
  String _filter = '';

  String _label(String key) => key.startsWith('!') ? key.substring(1) : '${AppTranslations.t(key)}';

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = widget.items
        .where((e) => _filter.isEmpty || _label(e.$1).toLowerCase().contains(_filter.toLowerCase()))
        .toList();
    return Dialog(
      child: Container(
        width: 480,
        height: 500,
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(
              controller: _query,
              autofocus: true,
              decoration: InputDecoration(
                hintText: '${AppTranslations.t('command.placeholder')}',
                prefixIcon: const Icon(Icons.search, size: 20),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _filter = v),
              onSubmitted: (_) {
                if (filtered.isNotEmpty) widget.onSelected(filtered.first.$3);
              },
            ),
            const SizedBox(height: 12),
            Expanded(
              child: filtered.isEmpty
                  ? Center(child: Text("${AppTranslations.t('app.no_data')}"))
                  : ListView.separated(
                      itemCount: filtered.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (_, i) {
                        final (key, icon, index) = filtered[i];
                        return ListTile(
                          dense: true,
                          leading: Icon(icon, size: 20),
                          title: Text(_label(key)),
                          trailing: Text('Alt+${index + 1}', style: Theme.of(context).textTheme.bodySmall),
                          onTap: () => widget.onSelected(index),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
