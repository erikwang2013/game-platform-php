// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_web_plugins/url_strategy.dart';
import 'package:get/get.dart';
import 'package:responsive_framework/responsive_framework.dart';
import 'app/theme/app_theme.dart';
import 'app/i18n/locale_controller.dart';
import 'app/routes/app_pages.dart';
import 'app/services/chat_service.dart';

void main() {
  if (kIsWeb) {
    usePathUrlStrategy();
  }
  Get.put(LocaleController());
  Get.put(ChatService(), permanent: true);
  runApp(const GamePlatformApp());
}

class GamePlatformApp extends StatelessWidget {
  const GamePlatformApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'Global Game Platform',
      locale: const Locale('en', 'US'),
      fallbackLocale: const Locale('en', 'US'),
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      builder: (context, child) => ResponsiveBreakpoints.builder(
        child: child!,
        breakpoints: [
          const Breakpoint(start: 0, end: 767, name: PHONE),
          const Breakpoint(start: 768, end: 1199, name: TABLET),
          const Breakpoint(start: 1200, end: 4500, name: DESKTOP),
        ],
      ),
      getPages: AppPages.routes,
      initialRoute: AppPages.initialRoute,
    );
  }
}
