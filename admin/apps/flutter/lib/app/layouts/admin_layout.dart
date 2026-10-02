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
import '../pages/risk/risk_manage_page.dart';
import '../pages/game_category/game_category_page.dart';
import '../pages/game_server/game_server_page.dart';
import '../pages/leaderboard/leaderboard_page.dart';
import '../pages/country_config/country_config_page.dart';
import '../pages/ticket/ticket_page.dart';
import '../pages/coupon/coupon_page.dart';

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

  /// 侧栏 / 命令面板 / 页面栈的**唯一**清单：加一个页面只改这里一行。
  ///
  /// 早先这里是三份并列的清单（`_pages` 的 25 个 Widget、`_paletteItems` 的 25 个元组、
  /// `_buildNavItems()` 的 25 个 `NavigationDrawerDestination` 约 130 行），三份的下标必须
  /// **严格一致**且没有任何东西在核 —— 漏改一处就是「侧栏点第 8 项打开第 9 个页面」这种
  /// 不报错、只能靠肉眼发现的坏法。现在下标就是位置，不存在对不齐。
  ///
  /// key 是翻译键；`!` 开头表示硬编码原文（见 `_navLabel`）。
  static const _nav = <(String, IconData, Widget)>[
    ('nav.dashboard', Icons.dashboard, DashboardPage()),
    ('nav.reports', Icons.bar_chart, ReportPage()),
    ('nav.users', Icons.people, UserListPage()),
    ('nav.roles', Icons.security, RoleListPage()),
    ('nav.config', Icons.settings, ConfigPage()),
    ('nav.logs', Icons.description, LogPage()),
    ('nav.games', Icons.games, GameListPage()),
    ('nav.withdraws', Icons.account_balance_wallet, WithdrawPage()),
    ('nav.platform_users', Icons.group, PlatformUserPage()),
    ('nav.identity', Icons.verified_user, IdentityPage()),
    ('nav.risk_logs', Icons.warning, RiskLogPage()),
    ('nav.risk_dashboard', Icons.monitor_heart, RiskDashboardPage()),
    ('nav.payments', Icons.payment, PaymentPage()),
    ('nav.cdn', Icons.cloud, CdnPage()),
    ('nav.announcements', Icons.campaign, AnnouncementPage()),
    ('nav.vip', Icons.workspace_premium, VipPage()),
    ('nav.achievements', Icons.emoji_events, AchievementPage()),
    ('nav.activities', Icons.local_activity, ActivityPage()),
    ('nav.game_categories', Icons.category, GameCategoryPage()),
    ('nav.game_servers', Icons.dns, GameServerPage()),
    ('nav.leaderboards', Icons.leaderboard, LeaderboardPage()),
    ('nav.country_configs', Icons.public, CountryConfigPage()),
    ('nav.tickets', Icons.confirmation_number, TicketPage()),
    ('nav.coupons', Icons.local_offer, CouponPage()),
    // 24：风控管理（写操作）—— 与 11 的「风控大盘」（只读看板）分开
    ('nav.risk_manage', Icons.gpp_maybe_outlined, RiskManagePage()),
  ];

  // 墓碑：「分析」(analytics) 缺一页 —— 本树**没有**这个模块，不是这里漏了一项。
  // 后端 12 个聚合端点俱在（config/route.php:245-256 的 /admin/v1/analytics/*：
  // overview / game-ranking / dau-trend / hourly-trend / action-distribution / revenue /
  // conversion / probability / retention / funnel / arpu / economy），另两棵树都已接：
  // react 的 pages/TabPage.tsx id="analytics"（nav.analytics）、angular 的 pages/analytics.ts。
  // 本树既无页面也无 `analytics.*` 词条（grep 全树 0 命中）—— 本轮刻意不建（代价是这 12 个
  // 端点在 flutter 端不可达），要补的话：先加词条、再在此处插一项、再照 angular 那页的
  // 分类（kpi / line / bars / funnel / table）建页；别只加导航项指到一个空页面。

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);
  bool get _isTablet => _bp.equals(TABLET);

  @override
  void initState() {
    super.initState();
    _currentChild = _nav[_selectedIndex].$3;
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
      _currentChild = _nav[index.clamp(0, _nav.length - 1)].$3;
    });
  }

  // ─── 命令面板 + 全局快捷键 ────────────────────────────────────────

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
      // LogicalKeyboardKey 无 >=/<= 运算符，按 keyId 偏移判定 Alt+1..9
      final n = key.keyId - LogicalKeyboardKey.digit1.keyId;
      if (n >= 0 && n <= 8) {
        _onNavChanged(n);
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
        items: _nav,
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

  /// 侧栏项由 [_nav] 生成 —— 图标与标题两边同源，改一处两边一起变。
  List<NavigationDrawerDestination> _buildNavItems() => [
        for (final (key, icon, _) in _nav)
          NavigationDrawerDestination(
            icon: Icon(icon, size: 20),
            label: Text(_navLabel(key)),
            selectedIcon: Icon(icon, size: 20),
          ),
      ];

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
        if (value.startsWith('lang:')) {
          localeCtrl.changeLocale(value.substring('lang:'.length));
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
                    final nav = Navigator.of(context);
                    Navigator.pop(ctx);
                    await AuthService.clearToken();
                    nav.pushReplacementNamed('/login');
                  },
                  child: Text("${AppTranslations.t('app.confirm')}",
                      style: const TextStyle(color: Colors.red)),
                ),
              ],
            ),
          );
        }
      },
      // 语言平铺进同一个菜单（不再是一个「中/英互切」的开关）：13 种由
      // LocaleController.supported 驱动，增删语言只改那一处；当前语言打点标记。
      // 用母语名而不是译名 —— 语言菜单要在「用户还看不懂当前界面语言」时也能选对。
      itemBuilder: (_) => [
        ...LocaleController.supported.map(
          (item) => PopupMenuItem(
            value: 'lang:${item.code}',
            child: Row(
              children: [
                Icon(
                  item.code == localeCtrl.currentLocale.value
                      ? Icons.radio_button_checked
                      : Icons.language,
                  size: 18,
                ),
                const SizedBox(width: 8),
                Text(item.nativeName),
              ],
            ),
          ),
        ),
        const PopupMenuDivider(),
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

/// 侧栏 / 命令面板共用的标题取值：key 是翻译键，`!` 开头表示硬编码原文
/// （历史遗留的几项还没有翻译键，剥掉 `!` 原样显示，别当成拼写错误删掉）。
String _navLabel(String key) =>
    key.startsWith('!') ? key.substring(1) : '${AppTranslations.t(key)}';

/// Ctrl+K 命令面板：输入即过滤，回车或点击跳转页面
class _CommandPalette extends StatefulWidget {
  final List<(String, IconData, Widget)> items;
  final ValueChanged<int> onSelected;
  const _CommandPalette({required this.items, required this.onSelected});

  @override
  State<_CommandPalette> createState() => _CommandPaletteState();
}

class _CommandPaletteState extends State<_CommandPalette> {
  final _query = TextEditingController();
  String _filter = '';

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // 带上原始下标：过滤后列表下标 ≠ 侧栏下标，跳转要的是后者
    final filtered = widget.items.indexed
        .where((e) =>
            _filter.isEmpty || _navLabel(e.$2.$1).toLowerCase().contains(_filter.toLowerCase()))
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
                if (filtered.isNotEmpty) widget.onSelected(filtered.first.$1);
              },
            ),
            const SizedBox(height: 12),
            Expanded(
              child: filtered.isEmpty
                  ? Center(child: Text("${AppTranslations.t('app.no_data')}"))
                  : ListView.separated(
                      itemCount: filtered.length,
                      separatorBuilder: (_, _) => const Divider(height: 1),
                      itemBuilder: (_, i) {
                        final (index, (key, icon, _)) = filtered[i];
                        return ListTile(
                          dense: true,
                          leading: Icon(icon, size: 20),
                          title: Text(_navLabel(key)),
                          // 只在前 9 项标注快捷键：_handleKey 只实现 Alt+1..9，
                          // 给其余 16 项渲染 Alt+10..25 是凭空造出按不出来的提示。
                          trailing: index < 9
                              ? Text('Alt+${index + 1}',
                                  style: Theme.of(context).textTheme.bodySmall)
                              : null,
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
