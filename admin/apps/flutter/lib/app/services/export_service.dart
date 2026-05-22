// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:dio/dio.dart';
import 'package:file_saver/file_saver.dart';

class ExportService {
  final Dio _dio;

  ExportService(this._dio);

  Future<void> exportExcel({
    required String table,
    required List<String> columns,
    Map<String, dynamic>? conditions,
  }) async {
    final response = await _dio.post(
      '/admin/export/excel',
      data: {
        'table': table,
        'columns': columns,
        'conditions': conditions ?? {},
      },
      options: Options(responseType: ResponseType.bytes),
    );

    final filename = 'export_${table}_${DateTime.now().millisecondsSinceEpoch}.xlsx';
    await FileSaver.instance.saveFile(name: filename, bytes: response.data, ext: 'xlsx');
  }

  Future<void> exportPdf({
    required String type,
    required String title,
    required Map<String, dynamic> data,
  }) async {
    final response = await _dio.post(
      '/admin/export/pdf',
      data: {
        'type': type,
        'title': title,
        'data': data,
      },
      options: Options(responseType: ResponseType.bytes),
    );

    final filename = 'export_${type}_${DateTime.now().millisecondsSinceEpoch}.pdf';
    await FileSaver.instance.saveFile(name: filename, bytes: response.data, ext: 'pdf');
  }
}
