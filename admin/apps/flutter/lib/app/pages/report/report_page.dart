// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../i18n/translations.dart';
import 'report_controller.dart';

class ReportPage extends GetView<ReportController> {
  const ReportPage({super.key});

  static const _summaryFields = [
    ('new_users', Icons.person_add, Color(0xFF1677FF)),
    ('deposit_amount', Icons.payments, Color(0xFF52C41A)),
    ('deposit_count', Icons.receipt_long, Color(0xFFFA8C16)),
    ('withdraw_amount', Icons.account_balance_wallet, Color(0xFF722ED1)),
    ('withdraw_count', Icons.outbox, Color(0xFF13C2C2)),
    ('exchange_amount', Icons.swap_horiz, Color(0xFFEB2F96)),
    ('play_count', Icons.sports_esports, Color(0xFF2F54EB)),
  ];

  @override
  Widget build(BuildContext context) {
    Get.put(ReportController());
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
                Text(AppTranslations.t('report.title').toString(),
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
                const Spacer(),
                OutlinedButton.icon(
                  onPressed: _pickDateRange,
                  icon: const Icon(Icons.date_range, size: 18),
                  label: Text('${controller.startText} ~ ${controller.endText}'),
                ),
                const SizedBox(width: 12),
                FilledButton.icon(
                  onPressed: controller.exportCsv,
                  icon: const Icon(Icons.download, size: 18),
                  label: Text('${AppTranslations.t('report.export')}'),
                ),
              ],
            ),
            const SizedBox(height: 24),
            _buildSummaryGrid(context),
            const SizedBox(height: 24),
            _buildDailyChart(context),
            const SizedBox(height: 24),
            _buildDailyTable(context),
          ],
        ),
      );
    });
  }

  Future<void> _pickDateRange() async {
    final picked = await showDateRangePicker(
      context: Get.context!,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      initialDateRange: DateTimeRange(start: controller.start, end: controller.end),
      helpText: '${AppTranslations.t('report.date_range')}',
    );
    if (picked != null) controller.setRange(picked.start, picked.end);
  }

  Widget _buildSummaryGrid(BuildContext context) {
    return LayoutBuilder(
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
          itemCount: _summaryFields.length,
          itemBuilder: (context, index) {
            final (key, icon, color) = _summaryFields[index];
            final value = controller.summary[key]?.toString() ?? '0';
            return Card(
              color: color.withValues(alpha: 0.08),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Icon(icon, color: color, size: 20),
                      const Spacer(),
                    ]),
                    const Spacer(),
                    Text("${AppTranslations.t('report.$key')}", style: TextStyle(fontSize: 13, color: color)),
                    const SizedBox(height: 4),
                    Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildDailyChart(BuildContext context) {
    final rows = controller.daily;
    if (rows.isEmpty) return const SizedBox.shrink();

    final deposits = rows.map((r) => _num(r['deposit_amount'])).toList();
    final withdraws = rows.map((r) => _num(r['withdraw_amount'])).toList();
    final maxY = [...deposits, ...withdraws].fold(0.0, (a, b) => a > b ? a : b);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('report.daily_chart')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 300,
              child: BarChart(
                BarChartData(
                  alignment: BarChartAlignment.spaceAround,
                  maxY: maxY * 1.2,
                  barGroups: rows.asMap().entries.map((e) {
                    final i = e.key;
                    return BarChartGroupData(
                      x: i,
                      barRods: [
                        BarChartRodData(toY: deposits[i], color: const Color(0xFF1677FF), width: 10),
                        BarChartRodData(toY: withdraws[i], color: const Color(0xFFFA8C16), width: 10),
                      ],
                    );
                  }).toList(),
                  titlesData: FlTitlesData(
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(showTitles: true, reservedSize: 44, getTitlesWidget: (v, _) => Text('${v.toInt()}')),
                    ),
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        reservedSize: 28,
                        getTitlesWidget: (v, meta) {
                          final i = v.toInt();
                          if (i % 5 != 0) return const SizedBox.shrink();
                          final date = rows[i]['date']?.toString() ?? '';
                          return Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(date.length >= 10 ? date.substring(5) : date, style: const TextStyle(fontSize: 11)),
                          );
                        },
                      ),
                    ),
                  ),
                  borderData: FlBorderData(show: false),
                  gridData: FlGridData(show: true, drawVerticalLine: false),
                  barTouchData: BarTouchData(enabled: false),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _legend(const Color(0xFF1677FF), '${AppTranslations.t('report.deposit_amount')}'),
                const SizedBox(width: 24),
                _legend(const Color(0xFFFA8C16), '${AppTranslations.t('report.withdraw_amount')}'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _legend(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 12, height: 12, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 12)),
      ],
    );
  }

  Widget _buildDailyTable(BuildContext context) {
    final rows = controller.daily;
    if (rows.isEmpty) return const SizedBox.shrink();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("${AppTranslations.t('report.daily')}", style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            SizedBox(
              height: 400,
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: SingleChildScrollView(
                  child: DataTable(
                    headingRowColor: WidgetStatePropertyAll(Theme.of(context).colorScheme.surfaceContainerHighest),
                    columns: [
                      _col('report.date'),
                      _col('report.deposit_amount'),
                      _col('report.deposit_count'),
                      _col('report.withdraw_amount'),
                      _col('report.withdraw_count'),
                      _col('report.play_count'),
                    ],
                    rows: rows.map((r) {
                      return DataRow(cells: [
                        _cell(r['date']?.toString() ?? '-'),
                        _cell(r['deposit_amount']?.toString() ?? '0'),
                        _cell(r['deposit_count']?.toString() ?? '0'),
                        _cell(r['withdraw_amount']?.toString() ?? '0'),
                        _cell(r['withdraw_count']?.toString() ?? '0'),
                        _cell(r['play_count']?.toString() ?? '0'),
                      ]);
                    }).toList(),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  DataColumn _col(String key) => DataColumn(label: Text('${AppTranslations.t(key)}'));

  DataCell _cell(String value) => DataCell(Text(value));

  double _num(dynamic v) => double.tryParse(v?.toString() ?? '') ?? 0;
}
