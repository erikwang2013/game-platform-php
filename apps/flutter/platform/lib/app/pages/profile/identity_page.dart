// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../services/user_file.dart';
import '../../widgets/user_file_image.dart';
import '../../i18n/translations.dart';

class IdentityPage extends StatefulWidget {
  const IdentityPage({super.key, this.pickImage});

  /// 选图来源，语义同 [ProfilePage.pickImage]：生产＝`UserFile.pickFromDevice`，未注入时按钮不渲染。
  final Future<PickedImage?> Function()? pickImage;

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
  bool _uploading = false;
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

  /// 选图 → 上传 → 写回该字段（存库值是相对 URL）。三照各自一个字段，成功后预览即时刷新。
  Future<void> _pickAndUpload(TextEditingController controller) async {
    final pick = widget.pickImage;
    if (pick == null) return;
    setState(() => _uploading = true);
    try {
      final picked = await pick();
      if (picked == null) return; // 用户取消选图
      final savedPath = await UserFile.uploadImage(fileName: picked.fileName, bytes: picked.bytes);
      if (!mounted) return;
      controller.text = UserFile.storedUrl(savedPath);
    } on ApiException catch (e) {
      if (mounted) Get.snackbar("${AppTranslations.t('app.error')}", e.message);
    } catch (_) {
      if (mounted) Get.snackbar("${AppTranslations.t('app.error')}", "${AppTranslations.t('app.upload_failed')}");
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  /// 证件照字段：输入框 + 上传按钮 + 鉴权预览（取不到时占位，别渲染成空白）
  Widget _photoField({
    required String label,
    required TextEditingController controller,
    bool required = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: TextFormField(
                controller: controller,
                decoration: InputDecoration(labelText: label),
                validator: required
                    ? (v) => (v == null || v.isEmpty) ? "${AppTranslations.t('identity.required')}" : null
                    : null,
              ),
            ),
            if (widget.pickImage != null) ...[
              const SizedBox(width: 12),
              SizedBox(
                height: 56,
                child: OutlinedButton.icon(
                  onPressed: _uploading ? null : () => _pickAndUpload(controller),
                  icon: _uploading
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.upload, size: 18),
                  label: Text("${AppTranslations.t('app.upload')}"),
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 8),
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: controller,
          builder: (_, value, __) => ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: UserFileImage(stored: value.text, width: 120, height: 90),
          ),
        ),
      ],
    );
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
          _photoField(
            label: "${AppTranslations.t('identity.front_photo')}",
            controller: _frontPhotoCtrl,
            required: true,
          ),
          const SizedBox(height: 16),
          _photoField(
            label: "${AppTranslations.t('identity.back_photo')}",
            controller: _backPhotoCtrl,
          ),
          const SizedBox(height: 16),
          _photoField(
            label: "${AppTranslations.t('identity.selfie_photo')}",
            controller: _selfiePhotoCtrl,
            required: true,
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
