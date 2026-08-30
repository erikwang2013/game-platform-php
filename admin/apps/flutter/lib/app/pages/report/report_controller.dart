// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/export_service.dart';

class ReportController extends GetxController {
  final api = ApiService();
  final isLoading = true.obs;
  final summary = <String, dynamic>{}.obs;
  final daily = <Map<String, dynamic>>[].obs;

  late DateTime _start = DateTime.now().subtract(const Duration(days: 29));
  late DateTime _end = DateTime.now();

  DateTime get start => _start;
  DateTime get end => _end;
  String get startText => _fmt(_start);
  String get endText => _fmt(_end);

  static String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    try {
      isLoading.value = true;
      final summaryResp = await api.get('/admin/report/summary', params: {'start': startText, 'end': endText});
      summary.value = Map<String, dynamic>.from(summaryResp['data'] ?? {});
      final dailyResp = await api.get('/admin/report/daily', params: {'start': startText, 'end': endText});
      daily.value = List<Map<String, dynamic>>.from(dailyResp['data'] ?? []);
    } catch (_) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}');
    } finally {
      isLoading.value = false;
    }
  }

  void setRange(DateTime start, DateTime end) {
    _start = start;
    _end = end;
    load();
  }

  Future<void> exportCsv() async {
    try {
      await ExportService(api.dio).exportReport(start: startText, end: endText);
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('report.exported')}');
    } catch (_) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.loading_failed')}');
    }
  }
}
