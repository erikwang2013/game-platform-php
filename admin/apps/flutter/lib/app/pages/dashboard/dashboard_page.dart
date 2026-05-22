// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../i18n/translations.dart';
import 'dashboard_controller.dart';

class DashboardPage extends GetView<DashboardController> {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    Get.put(DashboardController());
    return Obx(() {
      if (controller.isLoading.value) {
        return const Center(child: CircularProgressIndicator());
      }

      return SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(AppTranslations.t('dashboard.title').toString(),
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
                const Spacer(),
                PopupMenuButton<String>(
                  icon: const Icon(Icons.download),
                  tooltip: "${AppTranslations.t('dashboard.export')}",
                  onSelected: (type) {
                    if (type == 'pdf') controller.exportPdf();
                    if (type == 'excel') controller.exportExcel();
                  },
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: 'pdf', child: ListTile(leading: Icon(Icons.picture_as_pdf), title: Text("${AppTranslations.t('dashboard.export_pdf')}"), dense: true)),
                    PopupMenuItem(value: 'excel', child: ListTile(leading: Icon(Icons.table_chart), title: Text("${AppTranslations.t('dashboard.export_excel')}"), dense: true)),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 24),
            _buildStatsGrid(context),
            const SizedBox(height: 24),
            _buildTrendChart(context),
            const SizedBox(height: 24),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 2, child: _buildDistributionChart(context)),
                const SizedBox(width: 24),
                Expanded(flex: 3, child: _buildRecentLogs(context)),
              ],
            ),
            const SizedBox(height: 24),
            _buildPlatformStats(context),
          ],
        ),
      );
    });
  }

  Widget _buildStatsGrid(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = constraints.maxWidth > 900 ? 4 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: crossAxisCount,
            mainAxisExtent: 120,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
          ),
          itemCount: 4,
          itemBuilder: (context, index) {
            final stat = controller.stats[index];
            final color = Color(int.parse('0xFF${stat['color'].replaceFirst('#', '')}'));
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Icon(_getIcon(stat['icon']), color: color, size: 20),
                      const Spacer(),
                      if (stat['trend'] != null) _buildTrendBadge(stat['trend']),
                    ]),
                    const Spacer(),
                    Text(stat['label'], style: TextStyle(fontSize: 13, color: Colors.grey[600])),
                    const SizedBox(height: 4),
                    Text(stat['value'], style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildTrendChart(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('dashboard.data_trend')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 300,
              child: LineChart(
                LineChartData(
                  gridData: FlGridData(
                    show: true,
                    drawVerticalLine: false,
                    horizontalInterval: 10,
                  ),
                  titlesData: FlTitlesData(
                    bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: true, reservedSize: 40, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
                    ),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  ),
                  borderData: FlBorderData(show: false),
              lineBarsData: controller.trendSpots.map((spots) {
                    return LineChartBarData(
                      spots: spots,
                      color: const Color(0xFF1677FF),
                      barWidth: 2,
                      dotData: const FlDotData(show: false),
                      belowBarData: BarAreaData(
                        show: true,
                        color: const Color(0xFF1677FF).withValues(alpha: 0.1),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDistributionChart(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('dashboard.user_distribution')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 200,
              child: PieChart(
                PieChartData(
                  sections: controller.pieSections,
                  centerSpaceRadius: 40,
                  sectionsSpace: 2,
                ),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _buildLegend(const Color(0xFF1677FF), "${AppTranslations.t('app.enabled')}"),
                const SizedBox(width: 24),
                _buildLegend(const Color(0xFF52C41A), "${AppTranslations.t('app.disabled')}"),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLegend(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }

  Widget _buildRecentLogs(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('dashboard.recent_ops')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            ...controller.recentLogs.take(8).map((log) => ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: CircleAvatar(radius: 14, backgroundColor: const Color(0xFF1677FF).withValues(alpha: 0.1),
                  child: Text(log['user_name'][0].toUpperCase(), style: const TextStyle(fontSize: 12, color: Color(0xFF1677FF)))),
              title: Text(log['action'], style: const TextStyle(fontSize: 13)),
              subtitle: Text(log['created_at'] ?? '', style: const TextStyle(fontSize: 11)),
              trailing: Text(log['ip'] ?? '', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
            )),
          ],
        ),
      ),
    );
  }

  Widget _buildPlatformStats(BuildContext context) {
    final stats = controller.platformStats;
    if (stats.isEmpty) return const SizedBox.shrink();

    final totalUsers = stats['total_users']?.toString() ?? '0';
    final active7d = stats['active_7d']?.toString() ?? '0';
    final gameCount = stats['game_count']?.toString() ?? '0';
    final pendingWithdraw = stats['pending_withdraw_count']?.toString() ?? '0';
    final todayRecharge = stats['today_recharge']?.toString() ?? '0';
    final todayWithdraw = stats['today_withdraw']?.toString() ?? '0';
    final totalProfit = stats['total_profit']?.toString() ?? '0';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('dashboard.platform_stats')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            LayoutBuilder(
              builder: (context, constraints) {
                final crossAxisCount = constraints.maxWidth > 900 ? 4 : 2;
                return GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: crossAxisCount,
                    mainAxisExtent: 100,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                  ),
                  itemCount: 4,
                  itemBuilder: (context, index) {
                    final items = [
                      {'label': "${AppTranslations.t('dashboard.total_users')}", 'value': totalUsers, 'icon': Icons.group, 'color': const Color(0xFF1677FF)},
                      {'label': "${AppTranslations.t('dashboard.active_users')}", 'value': active7d, 'icon': Icons.trending_up, 'color': const Color(0xFF52C41A)},
                      {'label': "${AppTranslations.t('dashboard.total_games')}", 'value': gameCount, 'icon': Icons.games, 'color': const Color(0xFFFA8C16)},
                      {'label': "${AppTranslations.t('dashboard.pending_withdraws')}", 'value': pendingWithdraw, 'icon': Icons.account_balance_wallet, 'color': const Color(0xFF722ED1)},
                    ][index];
                    return Card(
                      elevation: 0,
                      color: items['color'] as Color? ?? Colors.grey,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(children: [
                              Icon(items['icon'] as IconData, color: Colors.white, size: 20),
                              const Spacer(),
                            ]),
                            const Spacer(),
                            Text(items['label'] as String, style: const TextStyle(fontSize: 13, color: Colors.white70)),
                            const SizedBox(height: 4),
                            Text(items['value'] as String, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: Colors.white)),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
            const SizedBox(height: 24),
            Row(
              children: [
                Expanded(
                  child: _buildFinanceCard("${AppTranslations.t('dashboard.today_deposits')}", todayRecharge, const Color(0xFF1677FF)),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildFinanceCard("${AppTranslations.t('dashboard.today_withdraws')}", todayWithdraw, const Color(0xFFFA8C16)),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildFinanceCard("${AppTranslations.t('dashboard.total_revenue')}", totalProfit, const Color(0xFF52C41A)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFinanceCard(String label, String value, Color color) {
    return Card(
      elevation: 0,
      color: color.withValues(alpha: 0.1),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(fontSize: 13, color: color)),
            const SizedBox(height: 8),
            Text(value, style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: color)),
          ],
        ),
      ),
    );
  }

  Widget _buildTrendBadge(double trend) {
    final isUp = trend >= 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: isUp ? Colors.green[50] : Colors.red[50],
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(isUp ? Icons.arrow_upward : Icons.arrow_downward, size: 12,
              color: isUp ? Colors.green : Colors.red),
          Text('${trend.abs()}%',
              style: TextStyle(fontSize: 11, color: isUp ? Colors.green : Colors.red)),
        ],
      ),
    );
  }

  IconData _getIcon(String name) {
    switch (name) {
      case 'people': return Icons.people;
      case 'person_add': return Icons.person_add;
      case 'bolt': return Icons.bolt;
      default: return Icons.description;
    }
  }
}
