// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

// `profile_page.dart` 的两块卡片 —— 从那个 561 行的文件里**原样搬**出来（超了 500 行纪律）。
//
// ⚠ 搬不是重写：文案键、字号、控件树与搬之前逐字相同，只是把局部状态换成入参。
// 两块都只被个人资料页用一次 ⇒ 不做成「通用卡片」，参数就是页面上那两个回调。
// 底部三张卡靠 ListTile / 带 Spacer 的 Row 撑满宽度，所以下面那个 Column 用 `stretch`：
// 接到紧宽度仍然通栏，与搬之前没有肉眼差。
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../i18n/translations.dart';

/// 资料信息卡：头像占位 + 用户名 + 五行只读字段
class ProfileInfoCard extends StatelessWidget {
  const ProfileInfoCard({super.key, required this.profile});

  final Map<String, dynamic>? profile;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Card(
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
                  child: Icon(
                    Icons.person,
                    size: 36,
                    color: colorScheme.onPrimaryContainer,
                  ),
                ),
                const SizedBox(width: 16),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      profile?['username'] ?? '-',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      "${AppTranslations.t('profile.account_info')}",
                      style: TextStyle(
                        fontSize: 13,
                        color: colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const Divider(height: 32),
            _buildInfoRow(
              context,
              "${AppTranslations.t('profile.username')}",
              profile?['username'] ?? '-',
            ),
            _buildInfoRow(
              context,
              "${AppTranslations.t('profile.nickname')}",
              profile?['nickname'] ?? '-',
            ),
            _buildInfoRow(
              context,
              "${AppTranslations.t('profile.country')}",
              profile?['country'] ?? '-',
            ),
            _buildInfoRow(
              context,
              "${AppTranslations.t('profile.language')}",
              profile?['language'] ?? '-',
            ),
            _buildInfoRow(
              context,
              "${AppTranslations.t('profile.registered')}",
              profile?['created_at'] ?? '-',
            ),
          ],
        ),
      ),
    );
  }
}

/// 2FA 入口 + 退出登录 + 注销账号（不可撤销）
class ProfileActionCards extends StatelessWidget {
  const ProfileActionCards({
    super.key,
    required this.onLogout,
    required this.onDeleteAccount,
  });

  final VoidCallback onLogout;
  final VoidCallback onDeleteAccount;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
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
                Text(
                  "${AppTranslations.t('profile.logout')}",
                  style: TextStyle(fontSize: 15, color: Colors.red),
                ),
                const Spacer(),
                OutlinedButton(
                  onPressed: onLogout,
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
                    const Icon(
                      Icons.delete_forever,
                      color: Colors.red,
                      size: 20,
                    ),
                    const SizedBox(width: 12),
                    Text(
                      "${AppTranslations.t('profile.delete_account')}",
                      style: const TextStyle(fontSize: 15, color: Colors.red),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  "${AppTranslations.t('profile.delete_account_warn')}",
                  style: TextStyle(
                    fontSize: 13,
                    color: colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: onDeleteAccount,
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                  child: Text("${AppTranslations.t('profile.delete_account')}"),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

Widget _buildInfoRow(BuildContext context, String label, String value) {
  return Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        SizedBox(
          width: 80,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 14))),
      ],
    ),
  );
}
