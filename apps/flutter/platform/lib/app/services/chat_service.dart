// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:async';
import 'dart:convert';
import 'package:get/get.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'api_helpers.dart';
import 'api_service.dart';
import 'auth_service.dart';

class ChatService extends GetxService {
  WebSocketChannel? _channel;
  Timer? _reconnectTimer;
  Timer? _pingTimer;
  final connected = false.obs;
  final unreadTotal = 0.obs;
  /// 对端 hashid → 本地收到的实时推送（REST 快照里还没有的那部分），见 handlePush
  final messagesByPeer = <String, RxList<Map<String, dynamic>>>{}.obs;
  int _reconnectDelay = 1;
  /// 连接代次：每次 connect/disconnect 自增。回调捕获建立时的代次，只有仍是最新代次才生效 ——
  /// 登出时关掉的旧通道，它的 onDone 会晚到；不判代次就会把刚建立的新连接再连一条。
  int _connSeq = 0;

  /// 聊天 WS 地址：默认沿用 ApiService.baseUrl 的 host + 8791（与服务端 CHAT_WS_PORT 对应），
  /// 可用 --dart-define=CHAT_WS_BASE_URL=ws://host:port 整串覆盖。
  static const String _chatWsBaseUrl = String.fromEnvironment('CHAT_WS_BASE_URL');

  static Uri resolveChatUri() {
    if (_chatWsBaseUrl.isNotEmpty) {
      return Uri.parse(_chatWsBaseUrl);
    }
    final baseUri = Uri.parse(ApiService.baseUrl);
    final scheme = baseUri.scheme == 'https' ? 'wss' : 'ws';
    return Uri.parse('$scheme://${baseUri.host}:8791');
  }

  Future<void> connect() async {
    final token = await AuthService.getToken();
    if (token == null) return;

    // 旧通道先关：换账号/重进大厅会再 connect 一次，不关就会留下第二条通道，
    // 且 _channel 被覆盖后旧的那条永远关不掉（旧身份的推送继续进桶 ⇒ 串消息）。
    _closeChannel();
    final seq = ++_connSeq;

    try {
      final channel = WebSocketChannel.connect(ChatService.resolveChatUri());
      _channel = channel;

      channel.stream.listen(
        (data) => _onMessage(data, seq),
        onDone: () => _onDisconnect(seq),
        onError: (e) => _onDisconnect(seq),
      );

      channel.sink.add(jsonEncode({'action': 'auth', 'token': token}));
      _startPing();
    } catch (_) {}
  }

  void _onMessage(dynamic data, int seq) {
    if (seq != _connSeq) return; // 上一代通道的迟到帧：身份可能已经换了，不得入桶
    try {
      final msg = jsonDecode(data as String);
      if (msg['type'] == 'authenticated') {
        connected.value = true;
        _reconnectDelay = 1;
        return;
      }
      if (msg['type'] == 'pong') return;
      if (msg['type'] == 'message' && msg['message'] != null) {
        handlePush(msg);
      }
    } catch (_) {}
  }

  /// 收下一帧 `type == 'message'` 的 WS 推送。
  /// 帧形状（ChatController::send 发布、ChatWebSocket 投递，逐字见 chat_push_merge_test.dart）：
  /// `{type:'message', message:{id, from_user_id, content, created_at}, to_user_id:<接收者的裸 int id>}`
  ///
  /// 键必须取 message.from_user_id：它是【对端】，且与 REST 同一形状（hashid 字符串），
  /// 与 chat_page 的 _peerId 键同域。不能取信封的 to_user_id —— 那是【接收者=自己】的裸 int
  /// （WS 进程拿它做投递路由），既不是对端、又与页面用的 hashid 键不同域，
  /// 消息会永远进不了对端的桶。
  void handlePush(Map<String, dynamic> frame) {
    final raw = frame['message'];
    if (raw is! Map) return;
    final m = Map<String, dynamic>.from(raw);
    final peerId = m['from_user_id'];
    if (peerId is! String || peerId.isEmpty) return;
    final list = messagesByPeer.putIfAbsent(peerId, () => <Map<String, dynamic>>[].obs);
    if (!list.any((e) => e['id'] == m['id'])) {
      list.add(m);
    }
  }

  /// 读取时合并：REST 快照 + 本地推送里 REST 尚未带回的那些（按 id 去重）。
  /// REST 是时间升序、推送的是最新一条，故追加在后。
  /// 依赖 messagesByPeer / 内层 RxList 的读取，调用方放进 Obx 即可随推送刷新。
  List<Map<String, dynamic>> mergeFor(String peerHashid, List<Map<String, dynamic>> rest) {
    final live = messagesByPeer[peerHashid];
    if (live == null || live.isEmpty) return rest;
    final seen = rest.map((e) => e['id']).whereType<Object>().toSet();
    final pending = live.where((e) => !seen.contains(e['id'])).toList();
    return pending.isEmpty ? rest : [...rest, ...pending];
  }

  void _startPing() {
    _pingTimer?.cancel();
    _pingTimer = Timer.periodic(const Duration(seconds: 25), (_) {
      try { _channel?.sink.add(jsonEncode({'action': 'ping'})); } catch (_) {}
    });
  }

  void _onDisconnect(int seq) {
    if (seq != _connSeq) return; // 登出/换代后旧通道的关闭事件：不得触发重连
    connected.value = false;
    _pingTimer?.cancel();
    _reconnectTimer?.cancel();
    _reconnectTimer = Timer(Duration(seconds: _reconnectDelay), () {
      if (_reconnectDelay < 15) _reconnectDelay *= 2;
      connect();
    });
  }

  /// 关掉当前通道与两个定时器（重连/换连接内部用），**不动**本地消息桶。
  void _closeChannel() {
    _reconnectTimer?.cancel();
    _pingTimer?.cancel();
    try { _channel?.sink.close(); } catch (_) {}
    _channel = null;
    connected.value = false;
  }

  /// 用户主动断开（登出收敛点 `ApiService.signOut` 调用）：关通道 + 清空本地推送桶与未读数。
  ///
  /// 只清 token 不断开的话：旧账号的通道还活着，推送继续以对端 hashid 为键进
  /// messagesByPeer；下一个账号再 connect() 会把 _channel 覆盖掉，旧通道永远关不掉 ——
  /// 两个账号都加过同一个好友时，新账号打开该会话会读到旧账号的消息。
  void disconnect() {
    _connSeq++; // 旧通道的回调就此作废
    _closeChannel();
    messagesByPeer.clear();
    unreadTotal.value = 0;
  }

  Future<List<Map<String, dynamic>>> loadConversations() async {
    final resp = await ApiService().get('/api/v1/chat/conversations');
    return ApiHelpers.extractList(resp['data']);
  }

  Future<List<Map<String, dynamic>>> loadMessages(String peerHashid, {int page = 1}) async {
    final resp = await ApiService().get('/api/v1/chat/messages/$peerHashid', params: {'page': page});
    return ApiHelpers.extractList(resp['data']);
  }

  Future<void> sendMessage(String peerHashid, String content) async {
    await ApiService().post('/api/v1/chat/send', data: {'to_user_id': peerHashid, 'content': content});
    try { await refreshUnread(); } catch (_) {}
  }

  Future<void> markRead(String peerHashid) async {
    // 服务端读的是 from_user_id（ChatController::markRead），发 peer_id 会落进 422 "Invalid user"
    await ApiService().post('/api/v1/chat/read', data: {'from_user_id': peerHashid});
    await refreshUnread();
  }

  Future<void> refreshUnread() async {
    try {
      final resp = await ApiService().get('/api/v1/chat/unread-total');
      unreadTotal.value = (resp['data']?['count'] as num?)?.toInt() ?? 0;
    } catch (_) {}
  }
}
