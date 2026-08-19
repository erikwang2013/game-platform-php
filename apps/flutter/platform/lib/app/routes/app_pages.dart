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
import '../pages/game/play_log_page.dart';
import '../pages/chat/chat_list_page.dart';
import '../pages/chat/chat_page.dart';
import '../pages/friend/friend_page.dart';
import '../pages/auth/two_factor_setup_page.dart';
import '../pages/auth/two_factor_verify_page.dart';
import '../pages/auth/oauth_callback_page.dart';
import '../pages/coupon/coupon_page.dart';
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
    GetPage(name: '/profile', page: () => const ProfilePage()),
    GetPage(name: '/identity', page: () => const IdentityPage()),
    GetPage(name: '/play-logs', page: () => const PlayLogPage()),
    GetPage(name: '/chat-list', page: () => const ChatListPage()),
    GetPage(name: '/chat', page: () => const ChatPage()),
    GetPage(name: '/friends', page: () => const FriendPage()),
    GetPage(name: '/2fa', page: () => const TwoFactorSetupPage()),
    GetPage(name: '/2fa-verify', page: () => const TwoFactorVerifyPage()),
    GetPage(name: '/coupons', page: () => const CouponPage()),
    GetPage(name: '/leaderboard', page: () => const LeaderboardPage()),
    GetPage(name: '/notifications', page: () => const NotificationPage()),
    GetPage(name: '/oauth/callback', page: () => const OAuthCallbackPage()),
  ];
}
