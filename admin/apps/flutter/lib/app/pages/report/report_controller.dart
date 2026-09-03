// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/export_service.dart';

class ReportController extends GetxController {
  static const int maxDays = 90;

  final api = ApiService();
  final isLoading = true.obs;
  final summary = <String, dynamic>{}.obs;
  final compare = <String, dynamic>{}.obs;
  final daily = <Map<String, dynamic>>[].obs;

  late DateTime _start = DateTime.now().subtract(const Duration(days: 29));
  late DateTime _end = DateTime.now();

  DateTime get start => _start;
  DateTime get end => _end;
  String get startText => _fmt(_start);
  String get endText => _fmt(_end);

  static String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  static int _days(DateTime a, DateTime b) => b.difference(a).inDays + 1;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    try {
      isLoading.value = true;
      final summaryResp = await api.get('/admin/v1/report/summary',
          params: {'start': startText, 'end': endText, 'compare': '1'});
      final data = Map<String, dynamic>.from(summaryResp['data'] ?? {});
      summary.value = data;
      compare.value = Map<String, dynamic>.from(data['compare'] ?? {});
      final dailyResp = await api.get('/admin/v1/report/daily', params: {'start': startText, 'end': endText});
      daily.value = List<Map<String, dynamic>>.from(dailyResp['data'] ?? []);
    } catch (_) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}');
    } finally {
      isLoading.value = false;
    }
  }

  void setRange(DateTime start, DateTime end) {
    if (_days(start, end) > maxDays) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('report.max_days')}');
      return;
    }
    _start = start;
    _end = end;
    load();
  }

  void setPreset(int days) {
    _start = DateTime.now().subtract(Duration(days: days - 1));
    _end = DateTime.now();
    load();
  }

  Future<void> exportCsv() => export('excel');

  Future<void> exportXlsx() => export('xlsx');

  Future<void> export(String format) async {
    try {
      await ExportService(api.dio).exportReport(start: startText, end: endText, format: format);
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('report.exported')}');
    } catch (_) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}');
    }
  }
}
