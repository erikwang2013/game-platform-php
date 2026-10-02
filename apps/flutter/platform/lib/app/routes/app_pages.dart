// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import '../pages/login/login_page.dart';
import '../pages/game/game_hall_page.dart';
import '../pages/game/game_detail_page.dart';
import '../pages/wallet/wallet_page.dart';
import '../pages/wallet/deposit_page.dart';
import '../pages/wallet/exchange_page.dart';
import '../pages/wallet/withdraw_page.dart';
import '../pages/profile/profile_page.dart';
import '../pages/profile/identity_page.dart';
import '../services/user_file.dart';
import '../pages/game/play_log_page.dart';
import '../pages/chat/chat_list_page.dart';
import '../pages/chat/chat_page.dart';
import '../pages/friend/friend_page.dart';
import '../pages/auth/two_factor_setup_page.dart';
import '../pages/auth/two_factor_verify_page.dart';
import '../pages/auth/oauth_callback_page.dart';
import '../pages/leaderboard/leaderboard_page.dart';
import '../pages/notification/notification_page.dart';

class AppPages {
  static const String initial = '/login';

  static String get initialRoute {
    if (kIsWeb) {
      final path = Uri.base.path;
      if (path.contains('oauth/callback')) return '/oauth/callback';
      for (final page in routes) {
        if (page.name == path) return path;
      }
    }
    return initial;
  }

  static final List<GetPage> routes = [
    GetPage(name: '/login', page: () => const LoginPage()),
    GetPage(name: '/games', page: () => const GameHallPage()),
    GetPage(name: '/game-detail', page: () => const GameDetailPage()),
    GetPage(name: '/wallet', page: () => const WalletPage()),
    GetPage(name: '/deposit', page: () => const DepositPage()),
    GetPage(name: '/exchange', page: () => const ExchangePage()),
    GetPage(name: '/withdraw', page: () => const WithdrawPage()),
    // 选图走 SDK 自带的 js_interop（无插件依赖）：web 弹系统选图框，非 web 恒返回 null（按取消处理）
    GetPage(name: '/profile', page: () => const ProfilePage(pickImage: UserFile.pickFromDevice)),
    GetPage(name: '/identity', page: () => const IdentityPage(pickImage: UserFile.pickFromDevice)),
    GetPage(name: '/play-logs', page: () => const PlayLogPage()),
    GetPage(name: '/chat-list', page: () => const ChatListPage()),
    GetPage(name: '/chat', page: () => const ChatPage()),
    GetPage(name: '/friends', page: () => const FriendPage()),
    GetPage(name: '/2fa', page: () => const TwoFactorSetupPage()),
    GetPage(name: '/2fa-verify', page: () => const TwoFactorVerifyPage()),
    // ⚠ 没有 `/coupons` 路由，是有意的（2026-10-02 撤下，与两棵 web 树对齐）：
    // `user_coupon` 的唯一行写入方就是 `CouponController::claim()` 本身
    // （admin 侧 `CouponController.php` 只删不发），即用户拿券的唯一途径是在那个页面点领取；
    // 而 `status='used'` 与 `used_in_order` 全仓无写入方、`used_qty` 只随领取递增
    // ⇒ 领了**永远用不掉**。页面能提供的全部价值 = 「在这领一张券，然后它永远躺着」= 假价值。
    // 后端做出核销/抵扣再恢复（C 端 react 树 `App.tsx` / `Layout.tsx` 是同一条墓碑）。
    GetPage(name: '/leaderboard', page: () => const LeaderboardPage()),
    GetPage(name: '/notifications', page: () => const NotificationPage()),
    GetPage(name: '/oauth/callback', page: () => const OAuthCallbackPage()),
  ];
}
