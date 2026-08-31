// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:async';
import 'dart:js_interop';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../services/api_service.dart';
import '../../i18n/translations.dart';

@JS('document.documentElement.requestFullscreen')
external JSPromise _requestFullscreen();

@JS('document.exitFullscreen')
external JSPromise _exitFullscreen();

@JS('document.fullscreenElement')
external JSObject? get _fullscreenElement;

/// 大屏模式：4 屏 15 秒轮播，F 键或按钮切换全屏，Esc 退出全屏（浏览器原生）。
class BigscreenPage extends StatefulWidget {
  const BigscreenPage({super.key});

  @override
  State<BigscreenPage> createState() => _BigscreenPageState();
}

class _BigscreenPageState extends State<BigscreenPage> {
  final _api = ApiService();
  int _screen = 0;
  bool _loading = true;
  Map<String, dynamic> _summary = {};
  Map<String, dynamic> _compare = {};
  List<Map<String, dynamic>> _daily = [];
  List<Map<String, dynamic>> _stats = [];
  Timer? _timer;

  static const _screens = 4;

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      setState(() => _screen = (_screen + 1) % _screens);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final s = await _api.get('/admin/report/summary',
          params: {'start': _fmt(DateTime.now().subtract(const Duration(days: 29))), 'end': _fmt(DateTime.now()), 'compare': '1'});
      final data = Map<String, dynamic>.from(s['data'] ?? {});
      _summary = data;
      _compare = Map<String, dynamic>.from(data['compare'] ?? {});
      final d = await _api.get('/admin/report/daily',
          params: {'start': _fmt(DateTime.now().subtract(const Duration(days: 29))), 'end': _fmt(DateTime.now())});
      _daily = List<Map<String, dynamic>>.from(d['data'] ?? []);
      final st = await _api.get('/admin/dashboard/stats');
      _stats = List<Map<String, dynamic>>.from(st['data']?['stats'] ?? []);
    } catch (_) {
      // 大屏静默降级：数据缺失时显示占位
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  static String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _toggleFullscreen() async {
    if (_fullscreenElement == null) {
      await _requestFullscreen();
    } else {
      await _exitFullscreen();
    }
    if (mounted) setState(() {});
  }

  double _scale(BuildContext context) => (MediaQuery.sizeOf(context).width / 1920).clamp(0.6, 2.0).toDouble();

  @override
  Widget build(BuildContext context) {
    final scale = _scale(context);
    return Scaffold(
      backgroundColor: const Color(0xFF0B1220),
      body: Focus(
        autofocus: true,
        onKeyEvent: (node, event) {
          if (event is KeyDownEvent && event.logicalKey == LogicalKeyboardKey.keyF) {
            _toggleFullscreen();
            return KeyEventResult.handled;
          }
          return KeyEventResult.ignored;
        },
        child: SafeArea(
          child: Padding(
            padding: EdgeInsets.all(24 * scale),
            child: Column(
              children: [
                _buildHeader(scale),
                SizedBox(height: 16 * scale),
                Expanded(child: _loading ? const Center(child: CircularProgressIndicator()) : _buildScreen(scale)),
                SizedBox(height: 12 * scale),
                _buildDots(scale),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(double scale) {
    final inFullscreen = _fullscreenElement != null;
    return Row(
      children: [
        Text('Game Platform', style: TextStyle(fontSize: 28 * scale, fontWeight: FontWeight.bold, color: Colors.white)),
        const Spacer(),
        Text("${AppTranslations.t('bigscreen.hint')}",
            style: TextStyle(fontSize: 13 * scale, color: Colors.white70)),
        const SizedBox(width: 16),
        IconButton(
          onPressed: _toggleFullscreen,
          icon: Icon(inFullscreen ? Icons.fullscreen_exit : Icons.fullscreen, color: Colors.white),
          tooltip: 'F',
        ),
        IconButton(
          onPressed: () => Navigator.of(context).pop(),
          icon: const Icon(Icons.close, color: Colors.white),
          tooltip: '${AppTranslations.t('app.close')}',
        ),
      ],
    );
  }

  Widget _buildScreen(double scale) {
    switch (_screen) {
      case 0:
        return _buildSummary(scale);
      case 1:
        return _buildTrend(scale);
      case 2:
        return _buildUsers(scale);
      default:
        return _buildPlatform(scale);
    }
  }

  Widget _buildDots(double scale) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(_screens, (i) {
        return AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          margin: EdgeInsets.symmetric(horizontal: 4 * scale),
          width: 10 * scale * (i == _screen ? 2.5 : 1),
          height: 10 * scale,
          decoration: BoxDecoration(
            color: i == _screen ? const Color(0xFF1677FF) : Colors.white24,
            borderRadius: BorderRadius.circular(5 * scale),
          ),
        );
      }),
    );
  }

  Widget _buildSummary(double scale) {
    const fields = [
      ('new_users', 'report.new_users'),
      ('deposit_amount', 'report.deposit_amount'),
      ('deposit_count', 'report.deposit_count'),
      ('withdraw_amount', 'report.withdraw_amount'),
      ('withdraw_count', 'report.withdraw_count'),
      ('exchange_amount', 'report.exchange_amount'),
      ('play_count', 'report.play_count'),
    ];
    return GridView.count(
      crossAxisCount: 4,
      mainAxisSpacing: 16 * scale,
      crossAxisSpacing: 16 * scale,
      childAspectRatio: 2.2,
      children: [
        for (final (key, label) in fields)
          _metricCard(
            '${AppTranslations.t(label)}',
            _summary[key]?.toString() ?? '0',
            _compare[key]?.toString(),
            scale,
          ),
      ],
    );
  }

  Widget _metricCard(String label, String value, String? prev, double scale) {
    final prevV = double.tryParse(prev ?? '') ?? 0;
    final curV = double.tryParse(value) ?? 0;
    final up = prevV > 0 && curV >= prevV;
    final pct = prevV > 0 ? ((curV - prevV) / prevV * 100).toStringAsFixed(1) : null;
    return Container(
      padding: EdgeInsets.all(20 * scale),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12 * scale),
        border: Border.all(color: Colors.white12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(label, style: TextStyle(fontSize: 16 * scale, color: Colors.white70)),
          const Spacer(),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(value, style: TextStyle(fontSize: 34 * scale, fontWeight: FontWeight.bold, color: Colors.white)),
                if (pct != null) ...[
                  SizedBox(width: 12 * scale),
                  Text('${up ? '↑' : '↓'} $pct%',
                      style: TextStyle(fontSize: 16 * scale, color: up ? const Color(0xFF52C41A) : const Color(0xFFF5222D))),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTrend(double scale) {
    if (_daily.isEmpty) return const SizedBox.shrink();
    final deposits = _daily.map((r) => double.tryParse(r['deposit_amount']?.toString() ?? '') ?? 0).toList();
    final withdraws = _daily.map((r) => double.tryParse(r['withdraw_amount']?.toString() ?? '') ?? 0).toList();
    final maxY = [...deposits, ...withdraws].fold(0.0, (a, b) => a > b ? a : b);
    return _panel('${AppTranslations.t('report.daily_chart')}', scale, Column(
      children: [
        Expanded(
          child: Padding(
            padding: EdgeInsets.all(16 * scale),
            child: BarChart(
              BarChartData(
                alignment: BarChartAlignment.spaceAround,
                maxY: maxY * 1.2,
                barGroups: List.generate(_daily.length, (i) {
                  return BarChartGroupData(x: i, barRods: [
                    BarChartRodData(toY: deposits[i], color: const Color(0xFF1677FF), width: 14 * scale),
                    BarChartRodData(toY: withdraws[i], color: const Color(0xFFFA8C16), width: 14 * scale),
                  ]);
                }),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: true, reservedSize: 60 * scale,
                        getTitlesWidget: (v, _) => Text('${v.toInt()}', style: TextStyle(fontSize: 12 * scale, color: Colors.white54))),
                  ),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: true, reservedSize: 24 * scale,
                        getTitlesWidget: (v, meta) {
                          final i = v.toInt();
                          if (i % 5 != 0) return const SizedBox.shrink();
                          final date = _daily[i]['date']?.toString() ?? '';
                          return Padding(
                            padding: EdgeInsets.only(top: 8 * scale),
                            child: Text(date.length >= 10 ? date.substring(5) : date,
                                style: TextStyle(fontSize: 11 * scale, color: Colors.white54)),
                          );
                        }),
                  ),
                ),
                borderData: FlBorderData(show: false),
                gridData: FlGridData(show: true, drawVerticalLine: false, getDrawingHorizontalLine: (v) => FlLine(color: Colors.white12)),
              ),
            ),
          ),
        ),
        _legendRow([(const Color(0xFF1677FF), '${AppTranslations.t('report.deposit_amount')}'), (const Color(0xFFFA8C16), '${AppTranslations.t('report.withdraw_amount')}')], scale),
      ],
    ));
  }

  Widget _buildUsers(double scale) {
    if (_daily.isEmpty) return const SizedBox.shrink();
    final users = _daily.map((r) => (double.tryParse(r['new_users']?.toString() ?? '') ?? 0).toDouble()).toList();
    final plays = _daily.map((r) => (double.tryParse(r['play_count']?.toString() ?? '') ?? 0).toDouble()).toList();
    final maxY = [...users, ...plays].fold(0.0, (a, b) => a > b ? a : b);
    return _panel('${AppTranslations.t('bigscreen.users_trend')}', scale, Column(
      children: [
        Expanded(
          child: Padding(
            padding: EdgeInsets.all(16 * scale),
            child: LineChart(
              LineChartData(
                minY: 0,
                maxY: maxY * 1.2,
                lineBarsData: [
                  _line(users, const Color(0xFF52C41A), scale),
                  _line(plays, const Color(0xFFEB2F96), scale),
                ],
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: true, reservedSize: 60 * scale,
                        getTitlesWidget: (v, _) => Text('${v.toInt()}', style: TextStyle(fontSize: 12 * scale, color: Colors.white54))),
                  ),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(showTitles: true, reservedSize: 24 * scale,
                        getTitlesWidget: (v, meta) {
                          final i = v.toInt();
                          if (i % 5 != 0) return const SizedBox.shrink();
                          final date = _daily[i]['date']?.toString() ?? '';
                          return Padding(
                            padding: EdgeInsets.only(top: 8 * scale),
                            child: Text(date.length >= 10 ? date.substring(5) : date,
                                style: TextStyle(fontSize: 11 * scale, color: Colors.white54)),
                          );
                        }),
                  ),
                ),
                borderData: FlBorderData(show: false),
                gridData: FlGridData(show: true, drawVerticalLine: false, getDrawingHorizontalLine: (v) => FlLine(color: Colors.white12)),
              ),
            ),
          ),
        ),
        _legendRow([
          (const Color(0xFF52C41A), '${AppTranslations.t('report.new_users')}'),
          (const Color(0xFFEB2F96), '${AppTranslations.t('report.play_count')}'),
        ], scale),
      ],
    ));
  }

  LineChartBarData _line(List<double> values, Color color, double scale) {
    return LineChartBarData(
      spots: List.generate(values.length, (i) => FlSpot(i.toDouble(), values[i])),
      color: color,
      barWidth: 3 * scale,
      isCurved: true,
      dotData: const FlDotData(show: false),
      belowBarData: BarAreaData(show: true, color: color.withValues(alpha: 0.08)),
    );
  }

  Widget _buildPlatform(double scale) {
    final items = _stats.map((s) => (s['label']?.toString() ?? s['key']?.toString() ?? '-', s['value']?.toString() ?? '0')).toList();
    if (items.isEmpty) {
      return _panel('${AppTranslations.t('bigscreen.platform')}', scale,
          const Center(child: Text('--', style: TextStyle(color: Colors.white54))));
    }
    return _panel('${AppTranslations.t('bigscreen.platform')}', scale, GridView.count(
      crossAxisCount: 3,
      mainAxisSpacing: 16 * scale,
      crossAxisSpacing: 16 * scale,
      childAspectRatio: 2.6,
      children: [
        for (final (label, value) in items)
          _metricCard(label, value, null, scale),
      ],
    ));
  }

  Widget _panel(String title, double scale, Widget child) {
    return Container(
      padding: EdgeInsets.all(20 * scale),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12 * scale),
        border: Border.all(color: Colors.white12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: TextStyle(fontSize: 20 * scale, fontWeight: FontWeight.w600, color: Colors.white)),
          SizedBox(height: 12 * scale),
          Expanded(child: child),
        ],
      ),
    );
  }

  Widget _legendRow(List<(Color, String)> items, double scale) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (final (color, label) in items) ...[
          Container(width: 12 * scale, height: 12 * scale, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2 * scale))),
          SizedBox(width: 6 * scale),
          Text(label, style: TextStyle(fontSize: 13 * scale, color: Colors.white70)),
          const SizedBox(width: 24),
        ],
      ],
    );
  }
}
