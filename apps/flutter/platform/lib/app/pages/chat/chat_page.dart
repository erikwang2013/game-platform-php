// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/chat_service.dart';

class ChatPage extends StatefulWidget {
  const ChatPage({super.key});

  @override
  State<ChatPage> createState() => _ChatPageState();
}

class _ChatPageState extends State<ChatPage> {
  final _chat = Get.find<ChatService>();
  final _msgCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  late String _peerId;
  String _peerName = 'User';
  List<Map<String, dynamic>> _messages = [];
  bool _loading = true;
  /// 深链进来又拿不到 peer_id（例如手敲 /chat）：走有出口的错误态，不白屏
  bool _missingPeer = false;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments;
    // 深链/刷新：Get.arguments 只在当次导航的内存里活着，此时为 null；
    // 旧代码 `args as Map<String, dynamic>` 非空转换 ⇒ TypeError ⇒ 白屏且无出口。
    final peerId = (args is Map ? args['peer_id']?.toString() : null) ?? deepLinkParam('peer_id');
    if (peerId == null || peerId.isEmpty) {
      setState(() {
        _missingPeer = true;
        _loading = false;
      });
      return;
    }
    _peerId = peerId;
    _peerName = (args is Map ? args['peer_name'] as String? : null) ?? 'User';
    _loadMessages();
  }

  Future<void> _loadMessages() async {
    setState(() => _loading = true);
    try {
      _messages = await _chat.loadMessages(_peerId);
    } catch (_) {}
    if (!mounted) return;
    setState(() => _loading = false);
    // 已读回执是后台记账：失败不该打断会话，更不该变成未处理异步异常
    _chat.markRead(_peerId).catchError((_) {});
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty) return;
    _msgCtrl.clear();
    final bubble = <String, dynamic>{
      'content': text,
      'from_self': true,
      'created_at': DateTime.now().toIso8601String(),
    };
    setState(() => _messages.add(bubble));
    await _deliver(bubble, text);
  }

  /// 落库发送：失败把本地气泡标成「发送失败·点击重发」。
  /// 这条本地消息没有服务端 id，`mergeFor` 按 id 去重认不出它 —— 不标失败就等于
  /// 让用户以为发出去了，离开页面后它永久消失。
  Future<void> _deliver(Map<String, dynamic> bubble, String text) async {
    setState(() => bubble.remove('failed'));
    try {
      await _chat.sendMessage(_peerId, text);
    } catch (_) {
      if (!mounted) return;
      setState(() => bubble['failed'] = true);
    }
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  /// 深链进来拿不到 peer_id：给两个出口（消息列表 / 大厅），不白屏
  Widget _buildMissingPeer() {
    return Scaffold(
      appBar: AppBar(title: Text("${AppTranslations.t('chat.title')}")),
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text("${AppTranslations.t('app.loading_failed')}", style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: () => Get.offAllNamed('/chat-list'),
              child: Text("${AppTranslations.t('chat.title')}"),
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: () => Get.offAllNamed('/games'),
              child: Text("${AppTranslations.t('game_detail.back_to_hall')}"),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_missingPeer) return _buildMissingPeer();
    return Scaffold(
      appBar: AppBar(title: Text(_peerName)),
      body: Column(
        children: [
          Obx(() => _chat.connected.value
              ? const SizedBox.shrink()
              : Container(width: double.infinity, padding: const EdgeInsets.all(4), color: Colors.orange.shade100, child: Text("${AppTranslations.t('chat.reconnecting')}", textAlign: TextAlign.center))),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : Obx(() {
                    // 读取时合并：REST 快照 + WS 推送里 REST 尚未带回的消息（按 id 去重）。
                    // 放在 Obx 里，推送到达时本页无需重进即可刷新。
                    final shown = _chat.mergeFor(_peerId, _messages);
                    return ListView.builder(
                      controller: _scrollCtrl,
                      itemCount: shown.length,
                      itemBuilder: (_, i) {
                        final m = shown[i];
                        // 本地乐观插入的消息只有 from_self；服务端回读的消息只有 from_user_id（hashid）。
                        // 判自己是"与 peer 不同的那一方"，而不是"from_user_id 为空"——否则刷新后
                        // 自己发的消息会从右侧翻到左侧。
                        final isSelf = m['from_self'] == true ||
                            (m['from_user_id'] != null && m['from_user_id'] != _peerId);
                        final failed = m['failed'] == true;
                        return Align(
                          alignment: isSelf ? Alignment.centerRight : Alignment.centerLeft,
                          child: Column(
                            crossAxisAlignment: isSelf ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                            children: [
                              Container(
                                margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                decoration: BoxDecoration(
                                  color: isSelf ? Theme.of(context).colorScheme.primary : Colors.grey.shade200,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(m['content'] as String? ?? '',
                                    style: TextStyle(color: isSelf ? Colors.white : Colors.black87)),
                              ),
                              // 发送失败：气泡留着并明确标出可重发，而不是假装已发出后悄悄消失
                              if (failed)
                                TextButton.icon(
                                  onPressed: () => _deliver(m, m['content'] as String? ?? ''),
                                  icon: const Icon(Icons.refresh, size: 14, color: Colors.red),
                                  label: Text("${AppTranslations.t('app.network_error')}",
                                      style: const TextStyle(fontSize: 12, color: Colors.red)),
                                ),
                            ],
                          ),
                        );
                      },
                    );
                  }),
          ),
          Padding(
            padding: const EdgeInsets.all(8),
            child: Row(children: [
              Expanded(child: TextField(controller: _msgCtrl, decoration: InputDecoration(hintText: "${AppTranslations.t('chat.hint')}"), onSubmitted: (_) => _send())),
              IconButton(icon: const Icon(Icons.send), onPressed: _send),
            ]),
          ),
        ],
      ),
    );
  }
}
