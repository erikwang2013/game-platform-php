// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';
import '../../services/chat_service.dart';

class ChatListPage extends StatefulWidget {
  const ChatListPage({super.key});

  @override
  State<ChatListPage> createState() => _ChatListPageState();
}

class _ChatListPageState extends State<ChatListPage> {
  final _api = ApiService();
  final _chat = Get.find<ChatService>();
  List<Map<String, dynamic>> _conversations = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      _conversations = await _chat.loadConversations();
    } catch (_) {}
    setState(() => _loading = false);
    _chat.refreshUnread();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("${AppTranslations.t('chat.title')}"),
        actions: [
          IconButton(
            icon: const Icon(Icons.people),
            onPressed: () => Get.toNamed('/friends'),
            tooltip: "${AppTranslations.t('nav.friends')}",
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _conversations.isEmpty
                ? Center(child: Text("${AppTranslations.t('chat.empty')}"))
                : ListView.builder(
                    itemCount: _conversations.length,
                    itemBuilder: (_, i) {
                      final c = _conversations[i];
                      return ListTile(
                        leading: CircleAvatar(child: Text((c['peer_name'] as String? ?? '?')[0].toUpperCase())),
                        title: Text(c['peer_name'] as String? ?? 'User'),
                        subtitle: Text(c['last_message'] as String? ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
                        trailing: (c['unread_count'] as int? ?? 0) > 0
                            ? Chip(label: Text('${c['unread_count']}'))
                            : null,
                        onTap: () => Get.toNamed('/chat', arguments: {
                          'peer_id': c['peer_id'],
                          'peer_name': c['peer_name'],
                        }),
                      );
                    },
                  ),
      ),
    );
  }
}
