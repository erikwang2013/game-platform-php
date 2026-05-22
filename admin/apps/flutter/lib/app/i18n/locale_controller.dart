// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocaleController extends GetxController {
  static const _localeKey = 'app_locale';

  RxString currentLocale = 'en'.obs;

  @override
  void onInit() {
    super.onInit();
    _loadLocale();
  }

  Future<void> _loadLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_localeKey);
    if (saved != null && ['en', 'zh'].contains(saved)) {
      currentLocale.value = saved;
      Get.updateLocale(saved == 'zh' ? const Locale('zh', 'CN') : const Locale('en', 'US'));
    }
  }

  void changeLocale(String code) async {
    currentLocale.value = code;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeKey, code);
    Get.updateLocale(code == 'zh' ? const Locale('zh', 'CN') : const Locale('en', 'US'));
  }
}
