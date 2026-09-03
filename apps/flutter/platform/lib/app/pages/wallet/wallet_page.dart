// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import '../../i18n/translations.dart';
import '../../i18n/locale_controller.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../profile/profile_page.dart';

class WalletPage extends StatefulWidget {
  const WalletPage({super.key});

  @override
  State<WalletPage> createState() => _WalletPageState();
}

class _WalletPageState extends State<WalletPage> {
  final _api = ApiService();
  bool _loading = true;
  bool _txLoading = true;
  String? _error;
  Map<String, dynamic>? _wallet;
  List<Map<String, dynamic>> _transactions = [];
  String _username = '';

  static const double headerHeight = 56;
  static const double sidebarWidth = 220;

  ResponsiveBreakpointsData get _bp => ResponsiveBreakpoints.of(context);
  bool get _isPhone => _bp.smallerThan(TABLET);

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final name = await AuthService.getUsername();
    if (mounted) setState(() => _username = name ?? 'User');
    await Future.wait([_fetchWallet(), _fetchTransactions()]);
  }

  Future<void> _fetchWallet() async {
    try {
      final resp = await _api.get('/api/v1/wallet/info');
      setState(() {
        _wallet = resp['data'];
        _loading = false;
        _error = null;
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

  Future<void> _fetchTransactions() async {
    try {
      final resp = await _api.get('/api/v1/wallet/transactions');
      final data = resp['data'];
      setState(() {
        _transactions = data is List ? List<Map<String, dynamic>>.from(data) : [];
        _txLoading = false;
      });
    } catch (e) {
      setState(() => _txLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: _isPhone
          ? AppBar(
              title: GetBuilder<LocaleController>(
                builder: (_) => Text('${AppTranslations.t('wallet.title')}'),
              ),
              leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.offAllNamed('/games')),
              actions: [_buildUserMenu()],
            )
          : null,
      body: _isPhone
          ? _buildContent(colorScheme)
          : Row(
              children: [
                // Sidebar
                _buildSidebar(),
                Expanded(
                  child: Column(
                    children: [
                      _buildDesktopHeader(),
                      Expanded(child: _buildContent(colorScheme)),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildSidebar() {
    return Container(
      width: sidebarWidth,
      child: GetBuilder<LocaleController>(
        builder: (_) => NavigationDrawer(
          selectedIndex: 1,
          onDestinationSelected: (index) {
            switch (index) {
              case 0:
                Get.offAllNamed('/games');
                break;
              case 1:
                break;
              case 2:
                Get.toNamed('/chat-list');
                break;
              case 3:
                Get.toNamed('/friends');
                break;
              case 4:
                Get.to(() => const ProfilePage());
                break;
            }
          },
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
            NavigationDrawerDestination(
              icon: const Icon(Icons.sports_esports, size: 20),
              label: Text('${AppTranslations.t('nav.games')}'),
              selectedIcon: const Icon(Icons.sports_esports, size: 20),
            ),
            NavigationDrawerDestination(
              icon: const Icon(Icons.account_balance_wallet, size: 20),
              label: Text('${AppTranslations.t('nav.wallet')}'),
              selectedIcon: const Icon(Icons.account_balance_wallet, size: 20),
            ),
            NavigationDrawerDestination(
              icon: const Icon(Icons.chat_bubble_outline, size: 20),
              label: Text('${AppTranslations.t('nav.chat')}'),
              selectedIcon: const Icon(Icons.chat_bubble_outline, size: 20),
            ),
            NavigationDrawerDestination(
              icon: const Icon(Icons.people_outline, size: 20),
              label: Text('${AppTranslations.t('nav.friends')}'),
              selectedIcon: const Icon(Icons.people_outline, size: 20),
            ),
            NavigationDrawerDestination(
              icon: const Icon(Icons.person, size: 20),
              label: Text('${AppTranslations.t('nav.profile')}'),
              selectedIcon: const Icon(Icons.person, size: 20),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDesktopHeader() {
    return Container(
      height: headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border(bottom: BorderSide(color: Theme.of(context).dividerColor)),
      ),
      child: Row(
        children: [
          GetBuilder<LocaleController>(
            builder: (_) => Text('${AppTranslations.t('wallet.title')}',
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
          Get.to(() => const ProfilePage());
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

  Widget _buildContent(ColorScheme colorScheme) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            FilledButton.tonal(
              onPressed: _loadData,
              child: Text('${AppTranslations.t('app.retry')}'),
            ),
          ],
        ),
      );
    }

    final balance = (_wallet?['balance'] ?? 0).toDouble();
    final frozen = (_wallet?['frozen_balance'] ?? 0).toDouble();
    final currency = _wallet?['currency'] ?? 'USD';

    return Container(
      color: colorScheme.surfaceContainerLowest,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Balance card
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${AppTranslations.t('wallet.balance')}',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          _buildBalanceItem('${AppTranslations.t('wallet.available_balance')}',
                              balance.toStringAsFixed(2), currency.toString(), colorScheme.primary),
                          const SizedBox(width: 32),
                          _buildBalanceItem('${AppTranslations.t('wallet.frozen_balance')}',
                              frozen.toStringAsFixed(2), currency.toString(), colorScheme.error),
                        ],
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          FilledButton.icon(
                            onPressed: () => Get.toNamed('/deposit'),
                            icon: const Icon(Icons.add, size: 18),
                            label: Text('${AppTranslations.t('wallet.deposit')}'),
                          ),
                          const SizedBox(width: 12),
                          FilledButton.tonalIcon(
                            onPressed: () => Get.toNamed('/exchange'),
                            icon: const Icon(Icons.swap_horiz, size: 18),
                            label: Text('${AppTranslations.t('wallet.exchange')}'),
                          ),
                          const SizedBox(width: 12),
                          OutlinedButton.icon(
                            onPressed: () => Get.toNamed('/withdraw'),
                            icon: const Icon(Icons.upload, size: 18),
                            label: Text('${AppTranslations.t('wallet.withdraw')}'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // Transactions
              Text('${AppTranslations.t('wallet.transactions')}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 12),
              Card(
                child: _txLoading
                    ? const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    : _transactions.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.all(32),
                            child: Center(child: Text('${AppTranslations.t('wallet.no_transactions')}')),
                          )
                        : SingleChildScrollView(
                            scrollDirection: Axis.horizontal,
                            child: DataTable(
                              columns: [
                                DataColumn(label: Text('${AppTranslations.t('wallet.tx_type')}')),
                                DataColumn(label: Text('${AppTranslations.t('wallet.tx_amount')}')),
                                DataColumn(label: Text('${AppTranslations.t('wallet.tx_balance_after')}')),
                                DataColumn(label: Text('${AppTranslations.t('wallet.tx_time')}')),
                              ],
                              rows: _transactions.map((tx) {
                                return DataRow(cells: [
                                  DataCell(_buildTxTypeBadge(tx['type']?.toString() ?? '')),
                                  DataCell(Text(tx['amount']?.toString() ?? '-')),
                                  DataCell(Text(tx['balance_after']?.toString() ?? '-')),
                                  DataCell(Text(tx['created_at']?.toString() ?? '-')),
                                ]);
                              }).toList(),
                            ),
                          ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBalanceItem(String label, String amount, String currency, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(fontSize: 13, color: Theme.of(context).colorScheme.onSurfaceVariant)),
        const SizedBox(height: 4),
        Text.rich(
          TextSpan(
            children: [
              TextSpan(text: '$currency ', style: TextStyle(fontSize: 14, color: color)),
              TextSpan(text: amount, style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: color)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildTxTypeBadge(String type) {
    final colorScheme = Theme.of(context).colorScheme;
    Color bg;
    switch (type) {
      case 'deposit':
        bg = Colors.green.withValues(alpha: 0.1);
        break;
      case 'withdraw':
        bg = Colors.red.withValues(alpha: 0.1);
        break;
      case 'exchange':
        bg = Colors.blue.withValues(alpha: 0.1);
        break;
      default:
        bg = colorScheme.surfaceContainerHighest;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(4)),
      child: Text(type, style: const TextStyle(fontSize: 12)),
    );
  }
}
