// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:ui';
import 'package:dio/dio.dart';

class CaptchaService {
  final Dio _dio;

  CaptchaService(this._dio);

  Future<CaptchaData> generate({String difficulty = 'medium'}) async {
    final resp = await _dio.post('/api/captcha/generate', data: {
      'difficulty': difficulty,
    });
    if (resp.data['code'] != 0) throw Exception(resp.data['message']);
    return CaptchaData.fromJson(resp.data['data']);
  }

  Future<bool> verify(String key, List<Offset> clicks) async {
    final resp = await _dio.post('/api/captcha/verify', data: {
      'key': key,
      'clicks': clicks.map((c) => {'x': c.dx.round(), 'y': c.dy.round()}).toList(),
    });
    return resp.data['data']?['valid'] == true;
  }
}

class CaptchaData {
  final String key;
  final String imageBase64;
  final List<CaptchaTarget> targets;

  CaptchaData({required this.key, required this.imageBase64, required this.targets});

  factory CaptchaData.fromJson(Map<String, dynamic> json) {
    return CaptchaData(
      key: json['key'] as String,
      imageBase64: json['image'] as String,
      targets: (json['extra']?['texts'] as List?)
          ?.map((t) => CaptchaTarget.fromJson(t))
          .toList() ?? [],
    );
  }
}

class CaptchaTarget {
  final int order;
  final String text;

  CaptchaTarget({required this.order, required this.text});

  factory CaptchaTarget.fromJson(Map<String, dynamic> json) {
    return CaptchaTarget(
      order: json['order'] as int,
      text: json['text'] as String,
    );
  }
}

