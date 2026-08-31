// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';

/// 总览：指标卡 + 命中趋势折线（按 rule_type 分色）+ 动作分布饼图
class RiskOverviewController extends GetxController {
  final api = ApiService();
  final overview = Rx<Map<String, dynamic>?>(null);
  final trend = Rx<Map<String, dynamic>?>(null);
  final distribution = Rx<Map<String, dynamic>?>(null);
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final o = await api.get('/admin/risk/overview');
      overview.value = o['data'];
      final t = await api.get('/admin/risk/hit-trend');
      trend.value = t['data'];
      final d = await api.get('/admin/risk/action-distribution');
      distribution.value = d['data'];
    } catch (e) {
      Get.snackbar('加载失败', '$e');
    } finally {
      isLoading.value = false;
    }
  }
}

class RiskOverviewTab extends GetView<RiskOverviewController> {
  const RiskOverviewTab({super.key});

  static const _typeColors = [
    Colors.blue, Colors.red, Colors.orange, Colors.green,
    Colors.purple, Colors.teal, Colors.brown, Colors.indigo,
  ];

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<RiskOverviewController>()) Get.put(RiskOverviewController());
    final ctrl = controller;

    return Obx(() {
      if (ctrl.isLoading.value) return const Center(child: CircularProgressIndicator());
      final o = ctrl.overview.value;
      if (o == null) return const Center(child: Text('无数据'));
      final total = (o['total'] as Map<String, dynamic>?) ?? {};

      return SingleChildScrollView(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          _card('命中数', '${total['hits'] ?? 0}'),
          _card('阻断', '${total['blocked'] ?? 0}', Colors.red),
          _card('告警', '${total['warned'] ?? 0}', Colors.orange),
          _card('阻断率', '${o['block_rate'] ?? 0}%'),
        ]),
        const SizedBox(height: 16),
        _section('命中趋势（按规则类型分色）', _trendChart(ctrl.trend.value)),
        const SizedBox(height: 16),
        _section('动作分布', _pieChart(ctrl.distribution.value)),
      ]));
    });
  }

  Widget _card(String label, String value, [Color? color]) => Expanded(
    child: Card(child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: const TextStyle(fontSize: 12, color: Colors.grey)),
        const SizedBox(height: 4),
        Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
      ]),
    )),
  );

  Widget _section(String title, Widget child) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
    const SizedBox(height: 8),
    SizedBox(height: 260, width: double.infinity, child: child),
  ]);

  Widget _trendChart(Map<String, dynamic>? data) {
    final series = (data?['series'] as Map<String, dynamic>?) ?? {};
    final lines = <LineChartBarData>[];
    final colorIndex = <String, int>{};
    series.forEach((type, points) {
      colorIndex.putIfAbsent(type, () => colorIndex.length);
      final idxSpots = <FlSpot>[];
      for (var i = 0; i < (points as List).length; i++) {
        idxSpots.add(FlSpot(i.toDouble(), ((points[i] as Map)['hits'] as num).toDouble()));
      }
      final color = _typeColors[colorIndex[type]! % _typeColors.length];
      lines.add(LineChartBarData(
        spots: idxSpots,
        isCurved: false,
        color: color,
        barWidth: 2,
        dotData: const FlDotData(show: false),
        belowBarData: BarAreaData(show: false),
      ));
    });
    if (lines.isEmpty) return const Center(child: Text('暂无命中数据'));

    return LineChart(LineChartData(
      gridData: const FlGridData(show: true),
      titlesData: FlTitlesData(
        leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: true)),
        bottomTitles: const AxisTitles(sideTitles: SideTitles(showTitles: true)),
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      borderData: FlBorderData(show: true),
      lineBarsData: lines,
    ));
  }

  Widget _pieChart(Map<String, dynamic>? data) {
    final items = (data?['items'] as List<dynamic>?) ?? [];
    if (items.isEmpty) return const Center(child: Text('暂无数据'));
    final colors = [Colors.red, Colors.orange, Colors.blue];
    return PieChart(PieChartData(sections: [
      for (var i = 0; i < items.length; i++)
        PieChartSectionData(
          value: ((items[i] as Map)['count'] as num).toDouble(),
          color: colors[i % colors.length],
          title: '${(items[i] as Map)['action']}\n${(items[i] as Map)['ratio']}%',
          radius: 60,
        ),
    ]));
  }
}
