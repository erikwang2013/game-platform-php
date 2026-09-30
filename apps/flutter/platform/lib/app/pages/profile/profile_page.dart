// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import '../../i18n/translations.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/user_file.dart';
import '../../widgets/user_file_image.dart';

/// 注销请求体：字段名与 confirm 的值都是服务端契约
/// （service/app/api/v1/controller/UserController.php:163），客户端原样透传用户输入，由服务端裁决。
Map<String, dynamic> deleteAccountPayload(String password, String confirm) => {
      'password': password,
      'confirm': confirm,
    };

/// 注销后回读的判定：只有 401/404 才算「账号确已取不到」。
/// 其余（网络故障/5xx）一律无法判定，由调用方按「无法确认」处理——绝不把不确定当成功。
bool isAccountGoneStatus(int code) => code == 401 || code == 404;

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key, this.pickImage});

  /// 选图来源：生产走 [UserFile.pickFromDevice]（SDK 自带的 dart:js_interop，不引插件依赖；
  /// 非 web 平台恒返回 null ⇒ 按「用户取消」处理）。未注入时按钮不渲染，测试注入假字节流
  /// 即可离线跑通全链路。
  final Future<PickedImage?> Function()? pickImage;

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final _api = ApiService();
  final _nicknameCtrl = TextEditingController();
  final _avatarCtrl = TextEditingController();
  final _languageCtrl = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  bool _uploading = false;
  String? _error;
  String? _successMsg;
  Map<String, dynamic>? _profile;

  @override
  void initState() {
    super.initState();
    _fetchProfile();
  }

  @override
  void dispose() {
    _nicknameCtrl.dispose();
    _avatarCtrl.dispose();
    _languageCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchProfile() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final resp = await _api.get('/api/v1/user/profile');
      final data = resp['data'];
      if (mounted) {
        setState(() {
          _profile = data;
          _nicknameCtrl.text = data?['nickname'] ?? '';
          _avatarCtrl.text = data?['avatar'] ?? '';
          _languageCtrl.text = data?['language'] ?? '';
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = "${AppTranslations.t('app.loading_failed')}";
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
      _successMsg = null;
    });

    try {
      await _api.put('/api/v1/user/profile', data: {
        'nickname': _nicknameCtrl.text.trim(),
        'avatar': _avatarCtrl.text.trim(),
        'language': _languageCtrl.text.trim(),
      });
      setState(() {
        _saving = false;
        _successMsg = "${AppTranslations.t('profile.save_success')}";
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _saving = false;
      });
    } catch (e) {
      setState(() {
        _error = "${AppTranslations.t('app.network_error')}";
        _saving = false;
      });
    }
  }

  /// 选图 → 上传 → 写回输入框（存库值是相对 URL，仍由用户按「保存」提交，不替他提交）。
  Future<void> _pickAndUploadAvatar() async {
    final pick = widget.pickImage;
    if (pick == null) return;
    setState(() => _uploading = true);
    try {
      final picked = await pick();
      if (picked == null) return; // 用户取消选图
      final savedPath = await UserFile.uploadImage(fileName: picked.fileName, bytes: picked.bytes);
      if (!mounted) return;
      _avatarCtrl.text = UserFile.storedUrl(savedPath); // 预览由 ValueListenableBuilder 跟随
    } on ApiException catch (e) {
      // 插件的业务错（类型/大小不允许）是给人看的措辞，原样透出
      if (mounted) Get.snackbar("${AppTranslations.t('app.error')}", e.message);
    } catch (_) {
      if (mounted) Get.snackbar("${AppTranslations.t('app.error')}", "${AppTranslations.t('app.upload_failed')}");
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  /// 注销后回读：账号真注销了 ⇒ 资料接口必须已取不到。
  /// true = 确认已注销（401/404）；false = 仍读得到资料 ⇒ 注销没生效。
  /// 网络故障这类无法判定的失败原样抛出，由调用方按「无法确认」处理。
  Future<bool> _accountGone() async {
    try {
      await _api.get('/api/v1/user/profile');
      return false;
    } on ApiException catch (e) {
      if (isAccountGoneStatus(e.code)) return true;
      rethrow;
    }
  }

  /// 注销账号：需当前密码 + 输入 yes（服务端 UserController::deleteAccount 的请求体契约），
  /// 服务端拒绝原因原样展示；成功与否以回读为准，不以「请求发出去了」为准。
  Future<void> _deleteAccount() async {
    final pwCtrl = TextEditingController();
    final yesCtrl = TextEditingController();
    String? err;
    bool busy = false;

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) {
          Future<void> submit() async {
            setDialogState(() {
              busy = true;
              err = null;
            });

            String? msg;
            bool gone = false;
            try {
              await _api.post('/api/v1/user/delete-account',
                  data: deleteAccountPayload(pwCtrl.text, yesCtrl.text));
            } on ApiException catch (e) {
              // 服务端拒绝原因原样透出（如「请先提现所有余额后再注销账号」），不吞成「操作失败」
              msg = e.message;
            } catch (_) {
              msg = "${AppTranslations.t('app.network_error')}";
            }

            if (msg == null) {
              try {
                gone = await _accountGone();
              } catch (_) {
                msg = "${AppTranslations.t('profile.delete_unknown')}";
              }
            }

            if (!ctx.mounted) return;
            if (msg != null || !gone) {
              setDialogState(() {
                err = msg ?? "${AppTranslations.t('profile.delete_unconfirmed')}";
                busy = false;
              });
              return;
            }
            Navigator.pop(ctx, true);
          }

          return AlertDialog(
            title: Text("${AppTranslations.t('profile.delete_account')}"),
            content: SizedBox(
              width: 360,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "${AppTranslations.t('profile.delete_account_warn')}",
                    style: TextStyle(fontSize: 13, color: Theme.of(ctx).colorScheme.onSurfaceVariant),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: pwCtrl,
                    obscureText: true,
                    enabled: !busy,
                    decoration: InputDecoration(
                      labelText: "${AppTranslations.t('profile.delete_password')}",
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: yesCtrl,
                    enabled: !busy,
                    decoration: InputDecoration(
                      labelText: "${AppTranslations.t('profile.delete_confirm_hint')}",
                      hintText: 'yes',
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  if (err != null) ...[
                    const SizedBox(height: 12),
                    Row(children: [
                      const Icon(Icons.error_outline, color: Colors.red, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(err!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                      ),
                    ]),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: busy ? null : () => Navigator.pop(ctx, false),
                child: Text("${AppTranslations.t('app.cancel')}"),
              ),
              TextButton(
                onPressed: busy ? null : submit,
                child: Text("${AppTranslations.t('app.confirm')}", style: const TextStyle(color: Colors.red)),
              ),
            ],
          );
        },
      ),
    );

    pwCtrl.dispose();
    yesCtrl.dispose();

    if (confirmed == true) {
      await AuthService.clearToken();
      Get.offAllNamed('/login');
    }
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text("${AppTranslations.t('app.confirm_logout')}"),
        content: Text("${AppTranslations.t('app.confirm_logout')}"),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text("${AppTranslations.t('app.cancel')}")),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text("${AppTranslations.t('app.confirm')}", style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirm == true) {
      await AuthService.clearToken();
      Get.offAllNamed('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text("${AppTranslations.t('profile.title')}"),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Get.back()),
      ),
      body: Container(
        color: colorScheme.surfaceContainerLowest,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 800),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Profile info card
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  CircleAvatar(
                                    radius: 36,
                                    backgroundColor: colorScheme.primaryContainer,
                                    child: Icon(Icons.person, size: 36, color: colorScheme.onPrimaryContainer),
                                  ),
                                  const SizedBox(width: 16),
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _profile?['username'] ?? '-',
                                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        "${AppTranslations.t('profile.account_info')}",
                                        style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                              const Divider(height: 32),
                              _buildInfoRow("${AppTranslations.t('profile.username')}", _profile?['username'] ?? '-'),
                              _buildInfoRow("${AppTranslations.t('profile.nickname')}", _profile?['nickname'] ?? '-'),
                              _buildInfoRow("${AppTranslations.t('profile.country')}", _profile?['country'] ?? '-'),
                              _buildInfoRow("${AppTranslations.t('profile.language')}", _profile?['language'] ?? '-'),
                              _buildInfoRow("${AppTranslations.t('profile.registered')}", _profile?['created_at'] ?? '-'),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Edit form
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text("${AppTranslations.t('profile.edit')}", style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 16),

                              TextField(
                                controller: _nicknameCtrl,
                                decoration: InputDecoration(
                                  labelText: "${AppTranslations.t('profile.nickname')}",
                                  border: OutlineInputBorder(),
                                ),
                              ),
                              const SizedBox(height: 16),

                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: TextField(
                                      controller: _avatarCtrl,
                                      decoration: InputDecoration(
                                        labelText: "${AppTranslations.t('profile.avatar')}",
                                        hintText: "${AppTranslations.t('profile.avatar_hint')}",
                                        border: const OutlineInputBorder(),
                                      ),
                                    ),
                                  ),
                                  if (widget.pickImage != null) ...[
                                    const SizedBox(width: 12),
                                    SizedBox(
                                      height: 56,
                                      child: OutlinedButton.icon(
                                        onPressed: _uploading ? null : _pickAndUploadAvatar,
                                        icon: _uploading
                                            ? const SizedBox(
                                                width: 18,
                                                height: 18,
                                                child: CircularProgressIndicator(strokeWidth: 2),
                                              )
                                            : const Icon(Icons.upload, size: 18),
                                        label: Text("${AppTranslations.t('app.upload')}"),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 12),
                              // 存的是托管相对 URL ⇒ 必须带 token 取字节（历史绝对 URL 直接渲染）
                              ValueListenableBuilder<TextEditingValue>(
                                valueListenable: _avatarCtrl,
                                builder: (_, value, __) => ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: UserFileImage(
                                    stored: value.text,
                                    width: 96,
                                    height: 96,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 16),

                              TextField(
                                controller: _languageCtrl,
                                decoration: InputDecoration(
                                  labelText: "${AppTranslations.t('profile.language')}",
                                  hintText: "${AppTranslations.t('profile.language_hint')}",
                                  border: OutlineInputBorder(),
                                ),
                              ),
                              const SizedBox(height: 20),

                              // Success / Error messages
                              if (_successMsg != null) ...[
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Colors.green.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(children: [
                                    const Icon(Icons.check_circle, color: Colors.green, size: 18),
                                    const SizedBox(width: 8),
                                    Text(_successMsg!, style: const TextStyle(color: Colors.green, fontSize: 13)),
                                  ]),
                                ),
                                const SizedBox(height: 12),
                              ],
                              if (_error != null) ...[
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Colors.red.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(children: [
                                    const Icon(Icons.error_outline, color: Colors.red, size: 18),
                                    const SizedBox(width: 8),
                                    Expanded(child: Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13))),
                                  ]),
                                ),
                                const SizedBox(height: 12),
                              ],

                              // Save button
                              SizedBox(
                                height: 44,
                                child: FilledButton(
                                  onPressed: _saving ? null : _save,
                                  child: _saving
                                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                      : Text("${AppTranslations.t('app.save')}", style: TextStyle(fontSize: 16)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.security),
                          title: Text("${AppTranslations.t('two_factor.title')}"),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => Get.toNamed('/2fa'),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Logout
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              const Icon(Icons.logout, color: Colors.red, size: 20),
                              const SizedBox(width: 12),
                              Text("${AppTranslations.t('profile.logout')}", style: TextStyle(fontSize: 15, color: Colors.red)),
                              const Spacer(),
                              OutlinedButton(
                                onPressed: _logout,
                                style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                                child: Text("${AppTranslations.t('profile.logout')}"),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),

                      // 注销账号（不可撤销）
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  const Icon(Icons.delete_forever, color: Colors.red, size: 20),
                                  const SizedBox(width: 12),
                                  Text("${AppTranslations.t('profile.delete_account')}", style: const TextStyle(fontSize: 15, color: Colors.red)),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                "${AppTranslations.t('profile.delete_account_warn')}",
                                style: TextStyle(fontSize: 13, color: colorScheme.onSurfaceVariant),
                              ),
                              const SizedBox(height: 12),
                              OutlinedButton(
                                onPressed: _deleteAccount,
                                style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                                child: Text("${AppTranslations.t('profile.delete_account')}"),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          SizedBox(
            width: 80,
            child: Text(label, style: TextStyle(fontSize: 13, color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 14))),
        ],
      ),
    );
  }
}
