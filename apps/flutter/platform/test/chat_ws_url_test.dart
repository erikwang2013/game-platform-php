// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/services/chat_service.dart';

void main() {
  test('未传 CHAT_WS_BASE_URL 时，聊天 WS 地址沿用 ApiService.baseUrl 的 host + 8791', () {
    // ApiService.baseUrl 默认 http://games.test（api_service.dart 的 String.fromEnvironment 缺省值）
    // → 只取 scheme 与 host，
    // 端口固定 8791（对应服务端 CHAT_WS_PORT），baseUrl 里的路径不参与推导
    expect(ChatService.resolveChatUri().toString(), 'ws://games.test:8791');
  });
}
