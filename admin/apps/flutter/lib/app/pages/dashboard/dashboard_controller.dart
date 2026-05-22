// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:dio/dio.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import '../../i18n/translations.dart';

class DashboardController extends GetxController {
  final Dio _dio = Dio(BaseOptions(baseUrl: 'http://localhost:8787'));
  final isLoading = true.obs;

  final stats = <Map<String, dynamic>>[].obs;
  final trends = <String, dynamic>{}.obs;
  final recentLogs = <Map<String, dynamic>>[].obs;
  final platformStats = <String, dynamic>{}.obs;

  List<List<FlSpot>> get trendSpots {
    final allSeries = trends['series'] as List<dynamic>? ?? [];
    return allSeries.map((s) {
      final data = s['data'] as List<dynamic>? ?? [];
      return data.asMap().entries.map((e) => FlSpot(e.key.toDouble(), (e.value as num).toDouble())).toList();
    }).toList();
  }

  List<PieChartSectionData> get pieSections {
    return [
      PieChartSectionData(color: const Color(0xFF1677FF), value: 265, title: '', radius: 30),
      PieChartSectionData(color: const Color(0xFF52C41A), value: 35, title: '', radius: 30),
    ];
  }

  @override
  void onInit() {
    super.onInit();
    loadData();
  }

  Future<void> loadData() async {
    try {
      isLoading.value = true;
      final response = await _dio.get('/admin/dashboard');
      if (response.data['code'] == 0) {
        final data = response.data['data'];
        stats.value = List<Map<String, dynamic>>.from(data['stats'] ?? []);
        trends.value = Map<String, dynamic>.from(data['trends'] ?? {});
        recentLogs.value = List<Map<String, dynamic>>.from(data['recent_logs'] ?? []);
      }
      await loadPlatformStats();
    } catch (e) {
      // 开发环境使用模拟数据
      stats.value = [
        {'label': AppTranslations.t('dashboard.total_users').toString(), 'value': '1,236', 'icon': 'people', 'color': '#1677FF', 'trend': 12.5},
        {'label': AppTranslations.t('dashboard.active_users').toString(), 'value': '89', 'icon': 'bolt', 'color': '#FA8C16', 'trend': -3.2},
        {'label': AppTranslations.t('dashboard.total_games').toString(), 'value': '28', 'icon': 'person_add', 'color': '#52C41A', 'trend': null},
        {'label': "${AppTranslations.t('log.title')}", 'value': '452', 'icon': 'description', 'color': '#722ED1', 'trend': 8.0},
      ];
      trends.value = {
        'dates': List.generate(30, (i) => 'Day $i'),
        'series': [
          {
            'name': AppTranslations.t('dashboard.total_users').toString(),
            'data': List.generate(30, (i) => 800 + i * 15 + (i > 20 ? 20 : 0)),
          },
        ],
      };
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadPlatformStats() async {
    try {
      final resp = await _dio.get('/admin/dashboard/platform');
      if (resp.data['code'] == 0) {
        platformStats.value = Map<String, dynamic>.from(resp.data['data'] ?? {});
      }
    } catch (_) {}
  }

  Future<void> exportPdf() async {
    final pdf = pw.Document();
    pdf.addPage(pw.MultiPage(
      pageFormat: PdfPageFormat.a4.landscape,
      build: (ctx) => [
        pw.Header(text: AppTranslations.t('dashboard.export').toString()),
        pw.Paragraph(text: 'Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz'),
        for (final s in stats)
          pw.Row(children: [
            pw.Text(s['label']),
            pw.Text(s['value'], style: pw.TextStyle(fontWeight: pw.FontWeight.bold)),
          ]),
      ],
    ));
    await Printing.sharePdf(bytes: await pdf.save(), filename: 'dashboard_export.pdf');
  }

  Future<void> exportExcel() async {
    Get.snackbar("${AppTranslations.t('dashboard.export')}", "${AppTranslations.t('dashboard.export_excel')}");
  }
}
