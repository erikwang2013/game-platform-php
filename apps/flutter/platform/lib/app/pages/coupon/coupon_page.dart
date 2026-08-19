// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';
import '../../services/api_helpers.dart';
import '../../services/api_service.dart';

class CouponPage extends StatefulWidget {
  const CouponPage({super.key});

  @override
  State<CouponPage> createState() => _CouponPageState();
}

class _CouponPageState extends State<CouponPage> with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late final TabController _tabs;
  List<Map<String, dynamic>> _available = [];
  List<Map<String, dynamic>> _mine = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final availableResp = await _api.get('/api/coupon/available');
      final mineResp = await _api.get('/api/coupon/my');
      setState(() {
        _available = ApiHelpers.extractList(availableResp['data']);
        _mine = ApiHelpers.extractList(mineResp['data']);
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

  Future<void> _claim(String id) async {
    try {
      await _api.post('/api/coupon/claim', data: {'coupon_id': id});
      Get.snackbar('${AppTranslations.t('app.success')}', '${AppTranslations.t('coupon.claimed')}');
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
        title: Text('${AppTranslations.t('coupon.title')}'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: '${AppTranslations.t('coupon.available')}'),
            Tab(text: '${AppTranslations.t('coupon.my')}'),
          ],
        ),
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
              : TabBarView(
                  controller: _tabs,
                  children: [
                    _list(_available, available: true),
                    _list(_mine, available: false),
                  ],
                ),
    );
  }

  Widget _list(List<Map<String, dynamic>> items, {required bool available}) {
    if (items.isEmpty) {
      return Center(child: Text('${AppTranslations.t('coupon.empty')}'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (_, i) {
          final item = items[i];
          final coupon = item['coupon'] is Map ? Map<String, dynamic>.from(item['coupon'] as Map) : item;
          final name = '${coupon['name'] ?? '-'}';
          final value = '${coupon['value'] ?? ''}';
          final status = '${item['status'] ?? ''}';
          return Card(
            child: ListTile(
              title: Text(name),
              subtitle: Text('${AppTranslations.t('coupon.value')}: $value'
                  '${status.isNotEmpty ? '  ·  $status' : ''}'),
              trailing: available
                  ? FilledButton(
                      onPressed: () => _claim('${coupon['id'] ?? item['id']}'),
                      child: Text('${AppTranslations.t('coupon.claim')}'),
                    )
                  : null,
            ),
          );
        },
      ),
    );
  }
}
