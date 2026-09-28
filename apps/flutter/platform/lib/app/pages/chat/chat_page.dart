// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
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
  late String _peerName;
  List<Map<String, dynamic>> _messages = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>;
    _peerId = args['peer_id'] as String;
    _peerName = args['peer_name'] as String? ?? 'User';
    _loadMessages();
  }

  Future<void> _loadMessages() async {
    setState(() => _loading = true);
    try {
      _messages = await _chat.loadMessages(_peerId);
    } catch (_) {}
    setState(() => _loading = false);
    _chat.markRead(_peerId);
  }

  void _send() {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty) return;
    _msgCtrl.clear();
    setState(() {
      _messages.add({'content': text, 'from_self': true, 'created_at': DateTime.now().toIso8601String()});
    });
    _chat.sendMessage(_peerId, text);
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
                        return Align(
                          alignment: isSelf ? Alignment.centerRight : Alignment.centerLeft,
                          child: Container(
                            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            decoration: BoxDecoration(
                              color: isSelf ? Theme.of(context).colorScheme.primary : Colors.grey.shade200,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(m['content'] as String? ?? '',
                                style: TextStyle(color: isSelf ? Colors.white : Colors.black87)),
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
