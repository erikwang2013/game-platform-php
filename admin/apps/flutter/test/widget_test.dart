// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:admin_app/main.dart';

void main() {
  testWidgets('AdminApp 冒烟: 启动即渲染登录页 (默认 en 语言)', (tester) async {
    await tester.pumpWidget(const AdminApp());
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.text('Game Platform Admin'), findsOneWidget);
    expect(find.text('Username'), findsOneWidget);
    expect(find.byType(Scaffold), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
