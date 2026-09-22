// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/services/chat_service.dart';

void main() {
  test('未传 CHAT_WS_BASE_URL 时，聊天 WS 地址沿用 ApiService.baseUrl 的 host + 8791', () {
    // ApiService.baseUrl 默认 http://localhost:8792 → 推导值必须逐字节不变
    expect(ChatService.resolveChatUri().toString(), 'ws://localhost:8791');
  });
}
