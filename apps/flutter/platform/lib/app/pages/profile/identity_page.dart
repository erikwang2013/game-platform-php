// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../i18n/translations.dart';

class IdentityPage extends StatefulWidget {
  const IdentityPage({super.key});
  @override
  State<IdentityPage> createState() => _IdentityPageState();
}

class _IdentityPageState extends State<IdentityPage> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _idNumberCtrl = TextEditingController();
  final _frontPhotoCtrl = TextEditingController();
  final _backPhotoCtrl = TextEditingController();
  final _selfiePhotoCtrl = TextEditingController();
  final _countryCtrl = TextEditingController();
  String _idType = 'id_card';
  String _country = '';
  bool _isLoading = false;
  Map<String, dynamic>? _existingData;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _idNumberCtrl.dispose();
    _frontPhotoCtrl.dispose();
    _backPhotoCtrl.dispose();
    _selfiePhotoCtrl.dispose();
    _countryCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    try {
      final api = ApiService();
      final resp = await api.get('/api/v1/user/identity/status');
      final data = resp['data'];
      if (data != null && data['status'] != 'not_submitted') {
        setState(() => _existingData = data as Map<String, dynamic>?);
      }
    } catch (_) {}
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _isLoading = true);
    try {
      final api = ApiService();
      await api.post('/api/v1/user/identity/apply', data: {
        'real_name': _nameCtrl.text,
        'id_type': _idType,
        'id_number': _idNumberCtrl.text,
        'id_front_photo': _frontPhotoCtrl.text,
        'id_back_photo': _backPhotoCtrl.text,
        'selfie_photo': _selfiePhotoCtrl.text,
        'country': _country,
      });
      Get.snackbar("${AppTranslations.t('app.success')}", "${AppTranslations.t('identity.submitted')}");
      _loadStatus();
    } on ApiException catch (e) {
      Get.snackbar("${AppTranslations.t('app.error')}", e.message);
    } catch (_) {
      Get.snackbar("${AppTranslations.t('app.error')}", "${AppTranslations.t('identity.submit_failed')}");
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_existingData != null && _existingData!['status'] != 'rejected') {
      return _buildStatusCard();
    }
    return _buildForm();
  }

  Widget _buildStatusCard() {
    final status = _existingData!['status'] ?? '';
    final color = status == 'approved' ? Colors.green : Colors.orange;
    return Center(child: Card(
      child: Padding(padding: const EdgeInsets.all(32), child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(status == 'approved' ? Icons.verified : Icons.pending, size: 64, color: color),
        const SizedBox(height: 16),
        Text(status == 'approved' ? "${AppTranslations.t('identity.verified')}" : "${AppTranslations.t('identity.pending')}", style: TextStyle(fontSize: 20, color: color)),
        const SizedBox(height: 8),
        Text("${AppTranslations.t('identity.real_name')}: ${_existingData!['real_name'] ?? '***'}"),
        Text("${AppTranslations.t('identity.type')}: ${_existingData!['id_type'] ?? ''}"),
        if (_existingData!['review_note'] != null && (_existingData!['review_note'] as String).isNotEmpty)
          Text("${AppTranslations.t('identity.review_note')}: ${_existingData!['review_note']}"),
      ])),
    ));
  }

  Widget _buildForm() {
    return Scaffold(
      appBar: AppBar(title: Text("${AppTranslations.t('identity.title')}")),
      body: Center(child: SizedBox(width: 500, child: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(24), children: [
          if (_existingData?['status'] == 'rejected')
            Card(color: Colors.red.shade50, child: Padding(
              padding: const EdgeInsets.all(12),
              child: Text("${AppTranslations.t('identity.rejected')}: ${_existingData!['review_note'] ?? ''}"),
            )),
          const SizedBox(height: 12),
          TextFormField(
            controller: _nameCtrl,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.full_name')}"),
            validator: (v) => (v == null || v.isEmpty) ? "${AppTranslations.t('identity.required')}" : null,
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            value: _idType,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.id_type_label')}"),
            items: [
              DropdownMenuItem(value: 'id_card', child: Text("${AppTranslations.t('identity.id_card')}")),
              DropdownMenuItem(value: 'passport', child: Text("${AppTranslations.t('identity.passport')}")),
              DropdownMenuItem(value: 'driver_license', child: Text("${AppTranslations.t('identity.driver_license')}")),
            ],
            onChanged: (v) => setState(() => _idType = v!),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _idNumberCtrl,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.id_number')}"),
            validator: (v) => (v == null || v.isEmpty) ? "${AppTranslations.t('identity.required')}" : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _frontPhotoCtrl,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.front_photo')}"),
            validator: (v) => (v == null || v.isEmpty) ? "${AppTranslations.t('identity.required')}" : null,
          ),
          const SizedBox(height: 16),
          TextFormField(controller: _backPhotoCtrl, decoration: InputDecoration(labelText: "${AppTranslations.t('identity.back_photo')}")),
          const SizedBox(height: 16),
          TextFormField(
            controller: _selfiePhotoCtrl,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.selfie_photo')}"),
            validator: (v) => (v == null || v.isEmpty) ? "${AppTranslations.t('identity.required')}" : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _countryCtrl,
            decoration: InputDecoration(labelText: "${AppTranslations.t('identity.country')}"),
            onChanged: (v) => _country = v,
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _isLoading ? null : _submit,
            child: Text(_isLoading ? "${AppTranslations.t('identity.submitting')}" : "${AppTranslations.t('identity.submit')}"),
          ),
        ]),
      ))),
    );
  }
}
