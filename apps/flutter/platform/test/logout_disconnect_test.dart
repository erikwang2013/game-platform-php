// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:game_platform/app/services/api_service.dart';
import 'package:game_platform/app/services/auth_service.dart';
import 'package:game_platform/app/services/chat_service.dart';

/// 帧形状与 chat_push_merge_test.dart 同源（ChatController::send 发布、ChatWebSocket 投递）
const String _pushJson = '{"type":"message",'
    '"message":{"id":"aBcD12","from_user_id":"kR3nQ8",'
    '"content":"晚上开黑？","created_at":"2026-09-28 21:40:00"},'
    '"to_user_id":12345}';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('disconnect 清空本地推送桶：换账号后不得读到上一账号的消息', () {
    final chat = ChatService();
    chat.handlePush(jsonDecode(_pushJson) as Map<String, dynamic>);
    expect(chat.messagesByPeer.keys.toList(), ['kR3nQ8']); // 桶里确实有旧账号的推送
    expect(chat.mergeFor('kR3nQ8', const []).length, 1);

    chat.disconnect();

    expect(chat.messagesByPeer, isEmpty);
    expect(chat.connected.value, isFalse);
  });

  test('登出收敛点 ApiService.signOut：断开聊天 WS 与清 token 一起做', () async {
    FlutterSecureStorage.setMockInitialValues({'access_token': 'A', 'refresh_token': 'RA'});
    expect(await AuthService.getToken(), 'A');

    final chat = ChatService();
    chat.handlePush(jsonDecode(_pushJson) as Map<String, dynamic>);
    Get.put(chat);

    await ApiService.signOut();

    expect(await AuthService.getToken(), isNull); // 登出确实发生了
    expect(chat.messagesByPeer, isEmpty); // 且 WS 同时被断开
  });

  test('登出只许走 ApiService.signOut：lib 里别处不得再直接 clearToken', () {
    const allowed = {
      'lib/app/services/auth_service.dart', // 定义
      'lib/app/services/api_service.dart', // 收敛点内部
    };
    final offenders = <String>[];
    for (final entity in Directory('lib').listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      if (allowed.contains(entity.path)) continue;
      final lines = entity.readAsLinesSync();
      for (var i = 0; i < lines.length; i++) {
        if (lines[i].contains('clearToken')) offenders.add('${entity.path}:${i + 1} ${lines[i].trim()}');
      }
    }
    // 6 个登出入口曾各自只清 token ⇒ 聊天 WS 不断开、换账号串消息。
    // 新增任何登出路径都必须走 ApiService.signOut()
    expect(offenders, isEmpty, reason: '登出必须走 ApiService.signOut()');
  });
}
