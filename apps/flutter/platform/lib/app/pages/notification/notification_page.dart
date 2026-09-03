// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_helpers.dart';
import '../../services/api_service.dart';

class NotificationPage extends StatefulWidget {
  const NotificationPage({super.key});

  @override
  State<NotificationPage> createState() => _NotificationPageState();
}

class _NotificationPageState extends State<NotificationPage> {
  final _api = ApiService();
  List<Map<String, dynamic>> _items = [];
  int _unread = 0;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final listResp = await _api.get('/api/v1/notification/list');
      final countResp = await _api.get('/api/v1/notification/unread-count');
      setState(() {
        _items = ApiHelpers.extractList(listResp['data']);
        _unread = (countResp['data']?['count'] as num?)?.toInt() ?? 0;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      setState(() {
        _error = '${AppTranslations.t('app.loading_failed')}';
        _loading = false;
      });
    }
  }

  Future<void> _markRead([String? id]) async {
    try {
      await _api.post('/api/v1/notification/read', data: id == null ? null : {'id': id});
      await _load();
    } on ApiException catch (e) {
      Get.snackbar('${AppTranslations.t('app.error')}', e.message);
    } catch (_) {
      Get.snackbar('${AppTranslations.t('app.error')}', '${AppTranslations.t('app.network_error')}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${AppTranslations.t('notification.title')}'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
        actions: [
          if (_unread > 0)
            TextButton(
              onPressed: () => _markRead(),
              child: Text('${AppTranslations.t('notification.mark_all_read')}'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                      const SizedBox(height: 12),
                      FilledButton.tonal(onPressed: _load, child: Text('${AppTranslations.t('app.retry')}')),
                    ],
                  ),
                )
              : _items.isEmpty
                  ? Center(child: Text('${AppTranslations.t('notification.empty')}'))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        itemCount: _items.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (_, i) {
                          final item = _items[i];
                          final unread = item['is_read'] == 0 || item['is_read'] == false;
                          return ListTile(
                            leading: Icon(unread ? Icons.notifications_active : Icons.notifications_none),
                            title: Text('${item['title'] ?? ''}'),
                            subtitle: Text('${item['content'] ?? item['created_at'] ?? ''}'),
                            trailing: unread
                                ? TextButton(
                                    onPressed: () => _markRead('${item['id']}'),
                                    child: Text('${AppTranslations.t('notification.mark_read')}'),
                                  )
                                : null,
                            onTap: unread ? () => _markRead('${item['id']}') : null,
                          );
                        },
                      ),
                    ),
    );
  }
}
