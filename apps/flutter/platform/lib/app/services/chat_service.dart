// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:async';
import 'dart:convert';
import 'package:get/get.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'api_service.dart';
import 'auth_service.dart';

class ChatService extends GetxService {
  WebSocketChannel? _channel;
  Timer? _reconnectTimer;
  Timer? _pingTimer;
  final connected = false.obs;
  final unreadTotal = 0.obs;
  final messagesByPeer = <int, RxList<Map<String, dynamic>>>{}.obs;
  int _reconnectDelay = 1;

  Future<void> connect() async {
    final token = await AuthService.getToken();
    if (token == null) return;

    try {
      final baseUri = Uri.parse(ApiService.baseUrl);
      final scheme = baseUri.scheme == 'https' ? 'wss' : 'ws';
      _channel = WebSocketChannel.connect(Uri.parse('$scheme://${baseUri.host}:8791'));

      _channel!.stream.listen(
        _onMessage,
        onDone: _onDisconnect,
        onError: (e) => _onDisconnect(),
      );

      _channel!.sink.add(jsonEncode({'action': 'auth', 'token': token}));
      _startPing();
    } catch (_) {}
  }

  void _onMessage(dynamic data) {
    try {
      final msg = jsonDecode(data as String);
      if (msg['type'] == 'authenticated') {
        connected.value = true;
        _reconnectDelay = 1;
        return;
      }
      if (msg['type'] == 'pong') return;
      if (msg['type'] == 'message' && msg['message'] != null) {
        final m = msg['message'] as Map<String, dynamic>;
        final peerId = _decodePeerId(msg);
        if (peerId != null) {
          messagesByPeer.putIfAbsent(peerId, () => <Map<String, dynamic>>[].obs);
          final list = messagesByPeer[peerId]!;
          if (!list.any((e) => e['id'] == m['id'])) {
            list.add(m);
          }
        }
      }
    } catch (_) {}
  }

  int? _decodePeerId(Map<String, dynamic> msg) {
    final toId = msg['to_user_id'];
    if (toId is int) return toId;
    if (toId is String) return int.tryParse(toId);
    return null;
  }

  void _startPing() {
    _pingTimer?.cancel();
    _pingTimer = Timer.periodic(const Duration(seconds: 25), (_) {
      try { _channel?.sink.add(jsonEncode({'action': 'ping'})); } catch (_) {}
    });
  }

  void _onDisconnect() {
    connected.value = false;
    _pingTimer?.cancel();
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(Duration(seconds: _reconnectDelay), () {
      if (_reconnectDelay < 15) _reconnectDelay *= 2;
      connect();
    });
  }

  void disconnect() {
    _reconnectTimer?.cancel();
    _pingTimer?.cancel();
    try { _channel?.sink.close(); } catch (_) {}
    _channel = null;
    connected.value = false;
  }

  Future<List<Map<String, dynamic>>> loadConversations() async {
    final resp = await ApiService().get('/api/v1/chat/conversations');
    return List<Map<String, dynamic>>.from(resp['data'] ?? []);
  }

  Future<List<Map<String, dynamic>>> loadMessages(String peerHashid, {int page = 1}) async {
    final resp = await ApiService().get('/api/v1/chat/messages/$peerHashid', params: {'page': page});
    return List<Map<String, dynamic>>.from(resp['data'] ?? []);
  }

  Future<void> sendMessage(String peerHashid, String content) async {
    await ApiService().post('/api/v1/chat/send', data: {'to_user_id': peerHashid, 'content': content});
    try { await refreshUnread(); } catch (_) {}
  }

  Future<void> markRead(String peerHashid) async {
    await ApiService().post('/api/v1/chat/read', data: {'peer_id': peerHashid});
    await refreshUnread();
  }

  Future<void> refreshUnread() async {
    try {
      final resp = await ApiService().get('/api/v1/chat/unread-total');
      unreadTotal.value = (resp['data'] ?? 0) as int;
    } catch (_) {}
  }
}
