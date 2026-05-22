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
  String _idType = 'id_card';
  String _country = '';
  bool _isLoading = false;
  Map<String, dynamic>? _existingData;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  Future<void> _loadStatus() async {
    try {
      final api = ApiService();
      final resp = await api.get('/user/identity/status');
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
      await api.post('/user/identity/apply', data: {
        'real_name': _nameCtrl.text,
        'id_type': _idType,
        'id_number': _idNumberCtrl.text,
        'id_front_photo': _frontPhotoCtrl.text,
        'id_back_photo': _backPhotoCtrl.text,
        'selfie_photo': _selfiePhotoCtrl.text,
        'country': _country,
      });
      Get.snackbar('Success', 'KYC submitted');
      _loadStatus();
    } catch (e) {
      Get.snackbar('Error', 'Submit failed: $e');
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
        Text(status == 'approved' ? 'Verified' : 'Under Review', style: TextStyle(fontSize: 20, color: color)),
        const SizedBox(height: 8),
        Text('Name: ${_existingData!['real_name'] ?? '***'}'),
        Text('Type: ${_existingData!['id_type'] ?? ''}'),
        if (_existingData!['review_note'] != null && (_existingData!['review_note'] as String).isNotEmpty)
          Text('Note: ${_existingData!['review_note']}'),
      ])),
    ));
  }

  Widget _buildForm() {
    return Scaffold(
      appBar: AppBar(title: const Text('KYC Verification')),
      body: Center(child: SizedBox(width: 500, child: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(24), children: [
          if (_existingData?['status'] == 'rejected')
            Card(color: Colors.red.shade50, child: Padding(
              padding: const EdgeInsets.all(12),
              child: Text('Rejected: ${_existingData!['review_note'] ?? "Please resubmit"}'),
            )),
          const SizedBox(height: 12),
          TextFormField(
            controller: _nameCtrl,
            decoration: const InputDecoration(labelText: 'Full Name'),
            validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            value: _idType,
            decoration: const InputDecoration(labelText: 'ID Type'),
            items: const [
              DropdownMenuItem(value: 'id_card', child: Text('ID Card')),
              DropdownMenuItem(value: 'passport', child: Text('Passport')),
              DropdownMenuItem(value: 'driver_license', child: Text('Driver License')),
            ],
            onChanged: (v) => setState(() => _idType = v!),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _idNumberCtrl,
            decoration: const InputDecoration(labelText: 'ID Number'),
            validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _frontPhotoCtrl,
            decoration: const InputDecoration(labelText: 'Front Photo URL'),
            validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(controller: _backPhotoCtrl, decoration: const InputDecoration(labelText: 'Back Photo URL (optional)')),
          const SizedBox(height: 16),
          TextFormField(
            controller: _selfiePhotoCtrl,
            decoration: const InputDecoration(labelText: 'Selfie with ID URL'),
            validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: TextEditingController(text: _country),
            decoration: const InputDecoration(labelText: 'Country (ISO code)'),
            onChanged: (v) => _country = v,
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _isLoading ? null : _submit,
            child: Text(_isLoading ? 'Submitting...' : 'Submit'),
          ),
        ]),
      ))),
    );
  }
}
