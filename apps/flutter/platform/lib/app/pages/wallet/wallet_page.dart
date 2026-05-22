// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
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
    if (mounted) setState(() => _username = name ?? '用户');
    await Future.wait([_fetchWallet(), _fetchTransactions()]);
  }

  Future<void> _fetchWallet() async {
    try {
      final resp = await _api.get('/api/wallet/info');
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
        _error = '加载失败，请重试';
        _loading = false;
      });
    }
  }

  Future<void> _fetchTransactions() async {
    try {
      final resp = await _api.get('/api/wallet/transactions');
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
              title: const Text('我的钱包'),
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
      child: NavigationDrawer(
        selectedIndex: 1,
        onDestinationSelected: (index) {
          switch (index) {
            case 0:
              Get.offAllNamed('/games');
              break;
            case 1:
              // Already on wallet
              break;
            case 2:
              Get.to(() => const ProfilePage());
              break;
          }
        },
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
          const NavigationDrawerDestination(
            icon: Icon(Icons.sports_esports, size: 20),
            label: Text('游戏大厅'),
            selectedIcon: Icon(Icons.sports_esports, size: 20),
          ),
          const NavigationDrawerDestination(
            icon: Icon(Icons.account_balance_wallet, size: 20),
            label: Text('我的钱包'),
            selectedIcon: Icon(Icons.account_balance_wallet, size: 20),
          ),
          const NavigationDrawerDestination(
            icon: Icon(Icons.person, size: 20),
            label: Text('个人中心'),
            selectedIcon: Icon(Icons.person, size: 20),
          ),
        ],
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
          const Text('我的钱包', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
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
            FilledButton.tonal(onPressed: _loadData, child: const Text('重试')),
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
                      Text('平台余额', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          _buildBalanceItem('可用余额', balance.toStringAsFixed(2), currency.toString(), colorScheme.primary),
                          const SizedBox(width: 32),
                          _buildBalanceItem('冻结余额', frozen.toStringAsFixed(2), currency.toString(), colorScheme.error),
                        ],
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          FilledButton.icon(
                            onPressed: () => Get.toNamed('/deposit'),
                            icon: const Icon(Icons.add, size: 18),
                            label: const Text('充值'),
                          ),
                          const SizedBox(width: 12),
                          FilledButton.tonalIcon(
                            onPressed: () => Get.toNamed('/exchange'),
                            icon: const Icon(Icons.swap_horiz, size: 18),
                            label: const Text('兑换'),
                          ),
                          const SizedBox(width: 12),
                          OutlinedButton.icon(
                            onPressed: () => Get.toNamed('/withdraw'),
                            icon: const Icon(Icons.upload, size: 18),
                            label: const Text('提现'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // Transactions
              Text('交易记录', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
              const SizedBox(height: 12),
              Card(
                child: _txLoading
                    ? const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    : _transactions.isEmpty
                        ? const Padding(
                            padding: EdgeInsets.all(32),
                            child: Center(child: Text('暂无交易记录')),
                          )
                        : SingleChildScrollView(
                            scrollDirection: Axis.horizontal,
                            child: DataTable(
                              columns: const [
                                DataColumn(label: Text('类型')),
                                DataColumn(label: Text('金额')),
                                DataColumn(label: Text('余额变化后')),
                                DataColumn(label: Text('时间')),
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
