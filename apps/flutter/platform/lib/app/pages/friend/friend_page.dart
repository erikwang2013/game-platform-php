// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_service.dart';

class FriendPage extends StatefulWidget {
  const FriendPage({super.key});

  @override
  State<FriendPage> createState() => _FriendPageState();
}

class _FriendPageState extends State<FriendPage> {
  final _api = ApiService();
  int _tab = 0;
  List<Map<String, dynamic>> _friends = [];
  List<Map<String, dynamic>> _requests = [];
  List<Map<String, dynamic>> _searchResults = [];
  bool _loading = false;
  final _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadFriends();
  }

  Future<void> _loadFriends() async {
    setState(() => _loading = true);
    try {
      final resp = await _api.get('/api/friend/list');
      _friends = List<Map<String, dynamic>>.from(resp['data'] ?? []);
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _loadRequests() async {
    setState(() => _loading = true);
    try {
      final resp = await _api.get('/api/friend/requests');
      _requests = List<Map<String, dynamic>>.from(resp['data'] ?? []);
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _search(String q) async {
    if (q.length < 2) return;
    try {
      final resp = await _api.get('/api/friend/search', params: {'q': q});
      _searchResults = List<Map<String, dynamic>>.from(resp['data'] ?? []);
    } catch (_) {}
    setState(() {});
  }

  Future<void> _sendRequest(String friendId) async {
    try {
      await _api.post('/api/friend/request', data: {'friend_id': friendId});
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('friend.request_sent')}');
    } catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', '$e');
    }
  }

  Future<void> _accept(String requestId) async {
    try {
      await _api.post('/api/friend/accept', data: {'request_id': requestId});
      _loadFriends();
      _loadRequests();
    } catch (_) {}
  }

  Future<void> _reject(String requestId) async {
    try {
      await _api.post('/api/friend/reject', data: {'request_id': requestId});
      _loadRequests();
    } catch (_) {}
  }

  Future<void> _remove(String friendId) async {
    try {
      await _api.post('/api/friend/remove', data: {'friend_id': friendId});
      _loadFriends();
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("${AppTranslations.t('nav.friends')}"),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Row(children: [
            _tabBtn("${AppTranslations.t('friend.tab_friends')}", 0),
            _tabBtn("${AppTranslations.t('friend.tab_requests')}", 1),
            _tabBtn("${AppTranslations.t('friend.tab_search')}", 2),
          ]),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _tab == 0
              ? _buildFriendList()
              : _tab == 1
                  ? _buildRequestList()
                  : _buildSearch(),
    );
  }

  Widget _tabBtn(String label, int idx) {
    final active = _tab == idx;
    return Expanded(child: GestureDetector(
      onTap: () {
        setState(() => _tab = idx);
        if (idx == 0) _loadFriends();
        if (idx == 1) _loadRequests();
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(border: Border(bottom: BorderSide(color: active ? Theme.of(context).colorScheme.primary : Colors.transparent, width: 2))),
        child: Text(label, textAlign: TextAlign.center, style: TextStyle(fontWeight: active ? FontWeight.bold : FontWeight.normal)),
      ),
    ));
  }

  Widget _buildFriendList() {
    if (_friends.isEmpty) return Center(child: Text("${AppTranslations.t('friend.no_friends')}"));
    return ListView.builder(
      itemCount: _friends.length,
      itemBuilder: (_, i) {
        final f = _friends[i];
        return ListTile(
          leading: const CircleAvatar(child: Icon(Icons.person)),
          title: Text(f['friend_name'] ?? 'User'),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(icon: const Icon(Icons.chat), onPressed: () => Get.toNamed('/chat', arguments: {'peer_id': f['friend_id'], 'peer_name': f['friend_name']})),
            IconButton(icon: const Icon(Icons.person_remove, color: Colors.red), onPressed: () => _remove(f['friend_id'])),
          ]),
        );
      },
    );
  }

  Widget _buildRequestList() {
    if (_requests.isEmpty) return Center(child: Text("${AppTranslations.t('friend.no_requests')}"));
    return ListView.builder(
      itemCount: _requests.length,
      itemBuilder: (_, i) {
        final r = _requests[i];
        return ListTile(
          leading: const CircleAvatar(child: Icon(Icons.person_add)),
          title: Text(r['user_name'] ?? 'User'),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(icon: const Icon(Icons.check, color: Colors.green), onPressed: () => _accept(r['id'])),
            IconButton(icon: const Icon(Icons.close, color: Colors.red), onPressed: () => _reject(r['id'])),
          ]),
        );
      },
    );
  }

  Widget _buildSearch() {
    return Column(children: [
      Padding(padding: const EdgeInsets.all(8), child: TextField(
        controller: _searchCtrl,
        decoration: InputDecoration(hintText: "${AppTranslations.t('friend.search_hint')}", prefixIcon: const Icon(Icons.search)),
        onChanged: (v) { if (v.length >= 2) _search(v); },
      )),
      Expanded(child: _searchResults.isEmpty
          ? Center(child: Text("${AppTranslations.t('friend.no_results')}"))
          : ListView.builder(
              itemCount: _searchResults.length,
              itemBuilder: (_, i) {
                final u = _searchResults[i];
                return ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.person)),
                  title: Text(u['username'] ?? 'User'),
                  trailing: ElevatedButton(
                    onPressed: () => _sendRequest(u['id']),
                    child: Text("${AppTranslations.t('friend.add')}"),
                  ),
                );
              },
            )),
    ]);
  }
}
