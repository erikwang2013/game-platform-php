// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'package:get/get.dart';

class AppTranslations {
  static const Map<String, Map<String, String>> _data = {
    'en': {
      // App
      'app.title': 'Global Game Platform',
      'app.logout': 'Logout',
      'app.confirm_logout': 'Are you sure you want to logout?',
      'app.cancel': 'Cancel',
      'app.confirm': 'Confirm',
      'app.save': 'Save',
      'app.delete': 'Delete',
      'app.edit': 'Edit',
      'app.create': 'Create',
      'app.search': 'Search',
      'app.close': 'Close',
      'app.yes': 'Yes',
      'app.no': 'No',
      'app.enabled': 'Enabled',
      'app.disabled': 'Disabled',
      'app.retry': 'Retry',
      'app.no_data': 'No data',
      'app.loading_failed': 'Loading failed, please retry',
      'app.network_error': 'Network error, please retry',
      'app.error': 'Error',
      'app.success': 'Success',

      // Sidebar / Nav
      'nav.games': 'Game Hall',
      'nav.wallet': 'My Wallet',
      'nav.identity': 'KYC',
      'nav.play_history': 'History',
      'nav.profile': 'Profile',
      'nav.chat': 'Chat',
      'nav.friends': 'Friends',
      'nav.coupons': 'Coupons',
      'nav.leaderboard': 'Leaderboard',
      'nav.notifications': 'Notifications',

      // Game Hall
      'game_hall.title': 'Game Hall',
      'game_hall.search_hint': 'Search games...',
      'game_hall.no_results': 'No matching games found',
      'game_hall.no_games': 'No games available',
      'game_hall.enter_game': 'Enter Game',

      // Game Detail
      'game_detail.title': 'Game Detail',
      'game_detail.start_game': 'Start Game',
      'game_detail.back_to_hall': 'Back to Game Hall',
      'game_detail.supported_currencies': 'Supported Currencies',
      'game_detail.exchange_rate': 'Exchange Rate',
      'game_detail.description': 'Description',

      // Wallet
      'wallet.title': 'My Wallet',
      'wallet.balance': 'Platform Balance',
      'wallet.available_balance': 'Available Balance',
      'wallet.frozen_balance': 'Frozen Balance',
      'wallet.deposit': 'Deposit',
      'wallet.exchange': 'Exchange',
      'wallet.withdraw': 'Withdraw',
      'wallet.transactions': 'Transactions',
      'wallet.no_transactions': 'No transactions',
      'wallet.tx_type': 'Type',
      'wallet.tx_amount': 'Amount',
      'wallet.tx_balance_after': 'Balance After',
      'wallet.tx_time': 'Time',

      // Deposit
      'deposit.title': 'Deposit',
      'deposit.subtitle': 'Select amount and payment method',
      'deposit.amount': 'Amount',
      'deposit.currency': 'Currency',
      'deposit.method': 'Payment Method',
      'deposit.submit': 'Submit Deposit',
      'deposit.success': 'Deposit request submitted',
      'deposit.order_no': 'Order No.',
      'deposit.invalid_amount': 'Please enter a valid amount',
      'deposit.enter_amount': 'Please enter deposit amount',

      // Exchange
      'exchange.title': 'Currency Exchange',
      'exchange.subtitle': 'Exchange platform balance with game currencies',
      'exchange.buy': 'Buy Game Currency',
      'exchange.sell': 'Sell Game Currency',
      'exchange.select_game': 'Select Game',
      'exchange.select_currency': 'Select Currency',
      'exchange.payment_amount': 'Payment Amount (Platform Coins)',
      'exchange.sell_amount': 'Sell Amount (Game Coins)',
      'exchange.get_quote': 'Get Quote',
      'exchange.confirm': 'Confirm Exchange',
      'exchange.quote_title': 'Exchange Quote',
      'exchange.rate': 'Exchange Rate',
      'exchange.from_amount': 'From Amount',
      'exchange.to_amount': 'To Amount',
      'exchange.fee': 'Fee',
      'exchange.success': 'Exchange Successful',
      'exchange.order_no': 'Transaction No.',

      // Withdraw
      'withdraw.title': 'Withdraw',
      'withdraw.subtitle': 'Withdraw your platform balance',
      'withdraw.amount': 'Amount',
      'withdraw.method': 'Withdraw Method',
      'withdraw.account_info': 'Account Info',
      'withdraw.paypal_email': 'PayPal Email Address',
      'withdraw.bank_info': 'Bank Account Info',
      'withdraw.crypto_address': 'Crypto Wallet Address',
      'withdraw.submit': 'Submit Withdraw Request',
      'withdraw.success': 'Withdraw request submitted',
      'withdraw.limits': 'Min: {min}  |  Daily Limit: {daily}',
      'withdraw.min_limit_error': 'Minimum withdrawal amount is ',
      'withdraw.daily_limit_error': 'Exceeds daily withdrawal limit of ',
      'withdraw.enter_account': 'Please enter account info',
      'withdraw.enter_amount': 'Please enter withdrawal amount',
      'withdraw.invalid_amount': 'Please enter a valid amount',

      // Profile
      'profile.title': 'Profile',
      'profile.edit': 'Edit Profile',
      'profile.nickname': 'Nickname',
      'profile.avatar': 'Avatar URL',
      'profile.avatar_hint': 'Enter avatar image URL',
      'profile.language': 'Language',
      'profile.language_hint': 'e.g. zh-CN, en-US',
      'profile.username': 'Username',
      'profile.country': 'Country',
      'profile.registered': 'Registered',
      'profile.account_info': 'Account Info',
      'profile.logout': 'Logout',
      'profile.save_success': 'Saved successfully',
      'profile.confirm_logout': 'Confirm Logout',
      'profile.confirm_logout_msg': 'Are you sure you want to logout?',

      // Login
      'login.title': 'Global Game Platform',
      'login.welcome': 'Welcome back',
      'login.create_account': 'Create New Account',
      'login.username': 'Username',
      'login.password': 'Password',
      'login.login': 'Log in',
      'login.register': 'Register',
      'login.have_account': 'Already have an account?',
      'login.no_account': 'No account?',
      'login.go_login': 'Go to Login',
      'login.go_register': 'Go to Register',
      'login.enter_credentials': 'Please enter username and password',
      'login.operation_failed': 'Operation failed',
      'login.oauth_google': 'Continue with Google',
      'login.oauth_facebook': 'Continue with Facebook',
      'login.oauth_apple': 'Continue with Apple',
      'login.or': 'or',
      'login.oauth_failed': 'OAuth login failed',
      'login.oauth_unavailable': 'OAuth is not configured',
      'login.welcome_new': 'Account created successfully',

      // 2FA
      'two_factor.title': 'Two-Factor Auth',
      'two_factor.verify_title': 'Verify 2FA',
      'two_factor.enabled': '2FA enabled',
      'two_factor.disabled': '2FA disabled',
      'two_factor.setup': 'Set up 2FA',
      'two_factor.enable': 'Enable',
      'two_factor.disable': 'Disable',
      'two_factor.secret': 'Copy secret',
      'two_factor.code': '6-digit code',
      'two_factor.password': 'Password',
      'two_factor.user_id': 'User ID',
      'two_factor.backup_codes': 'Backup codes',
      'two_factor.verify': 'Verify',
      'two_factor.enter_code': 'Please enter the 6-digit code',
      'two_factor.setup_hint': 'Add this secret to your authenticator app',

      // Coupon
      'coupon.title': 'Coupons',
      'coupon.available': 'Available',
      'coupon.my': 'My Coupons',
      'coupon.claim': 'Claim',
      'coupon.claimed': 'Coupon claimed',
      'coupon.empty': 'No coupons',
      'coupon.value': 'Value',

      // Leaderboard
      'leaderboard.title': 'Leaderboard',
      'leaderboard.empty': 'No rankings yet',

      // Notification
      'notification.title': 'Notifications',
      'notification.empty': 'No notifications',
      'notification.mark_read': 'Read',
      'notification.mark_all_read': 'Mark all read',

      // OAuth
      'oauth.processing': 'Completing sign-in...',
      'oauth.failed': 'OAuth callback failed',

      // Identity
      'identity.title': 'KYC Verification',
      'identity.verified': 'Verified',
      'identity.pending': 'Under Review',
      'identity.full_name': 'Full Name',
      'identity.id_type_label': 'ID Type',
      'identity.id_card': 'ID Card',
      'identity.passport': 'Passport',
      'identity.driver_license': 'Driver License',
      'identity.id_number': 'ID Number',
      'identity.front_photo': 'Front Photo URL',
      'identity.back_photo': 'Back Photo URL (optional)',
      'identity.selfie_photo': 'Selfie with ID URL',
      'identity.country': 'Country (ISO code)',
      'identity.required': 'Required',
      'identity.submit': 'Submit',
      'identity.submitting': 'Submitting...',
      'identity.submitted': 'KYC submitted',
      'identity.submit_failed': 'Submit failed, please retry',
      'identity.real_name': 'Name',
      'identity.type': 'Type',
      'identity.review_note': 'Note',
      'identity.rejected': 'Rejected',

      // Chat
      'chat.title': 'Messages',
      'chat.hint': 'Type a message...',
      'chat.empty': 'No conversations yet',
      'chat.reconnecting': 'Reconnecting...',

      // Friend
      'friend.tab_friends': 'Friends',
      'friend.tab_requests': 'Requests',
      'friend.tab_search': 'Search',
      'friend.search_hint': 'Search users...',
      'friend.add': 'Add',
      'friend.request_sent': 'Friend request sent',
      'friend.no_friends': 'No friends yet',
      'friend.no_requests': 'No pending requests',
      'friend.no_results': 'No users found',

      // Common
      'common.collapse_menu': 'Collapse menu',
      'common.expand_menu': 'Expand menu',
      'common.platform': 'Game Platform',
    },
    'zh': {
      // App
      'app.title': '全球游戏聚合平台',
      'app.logout': '退出登录',
      'app.confirm_logout': '确定要退出登录吗？',
      'app.cancel': '取消',
      'app.confirm': '确认',
      'app.save': '保存',
      'app.delete': '删除',
      'app.edit': '编辑',
      'app.create': '新建',
      'app.search': '搜索',
      'app.close': '关闭',
      'app.yes': '是',
      'app.no': '否',
      'app.enabled': '启用',
      'app.disabled': '禁用',
      'app.retry': '重试',
      'app.no_data': '暂无数据',
      'app.loading_failed': '加载失败，请重试',
      'app.network_error': '网络错误，请重试',
      'app.error': '错误',
      'app.success': '成功',

      // Sidebar / Nav
      'nav.games': '游戏大厅',
      'nav.wallet': '我的钱包',
      'nav.identity': '实名认证',
      'nav.play_history': '游戏记录',
      'nav.profile': '个人中心',
      'nav.chat': '聊天',
      'nav.friends': '好友',
      'nav.coupons': '优惠券',
      'nav.leaderboard': '排行榜',
      'nav.notifications': '通知',

      // Game Hall
      'game_hall.title': '游戏大厅',
      'game_hall.search_hint': '搜索游戏...',
      'game_hall.no_results': '未找到匹配的游戏',
      'game_hall.no_games': '暂无游戏',
      'game_hall.enter_game': '进入游戏',

      // Game Detail
      'game_detail.title': '游戏详情',
      'game_detail.start_game': '开始游戏',
      'game_detail.back_to_hall': '返回游戏大厅',
      'game_detail.supported_currencies': '支持的游戏币',
      'game_detail.exchange_rate': '兑换率',
      'game_detail.description': '描述',

      // Wallet
      'wallet.title': '我的钱包',
      'wallet.balance': '平台余额',
      'wallet.available_balance': '可用余额',
      'wallet.frozen_balance': '冻结余额',
      'wallet.deposit': '充值',
      'wallet.exchange': '兑换',
      'wallet.withdraw': '提现',
      'wallet.transactions': '交易记录',
      'wallet.no_transactions': '暂无交易记录',
      'wallet.tx_type': '类型',
      'wallet.tx_amount': '金额',
      'wallet.tx_balance_after': '余额变化后',
      'wallet.tx_time': '时间',

      // Deposit
      'deposit.title': '充值',
      'deposit.subtitle': '选择充值金额和支付方式',
      'deposit.amount': '充值金额',
      'deposit.currency': '币种',
      'deposit.method': '支付方式',
      'deposit.submit': '提交充值',
      'deposit.success': '充值申请已提交',
      'deposit.order_no': '订单号',
      'deposit.invalid_amount': '请输入有效的金额',
      'deposit.enter_amount': '请输入充值金额',

      // Exchange
      'exchange.title': '游戏币兑换',
      'exchange.subtitle': '将平台余额兑换为游戏币，或将游戏币兑换回余额',
      'exchange.buy': '买入游戏币',
      'exchange.sell': '卖出游戏币',
      'exchange.select_game': '选择游戏',
      'exchange.select_currency': '游戏币种',
      'exchange.payment_amount': '支付金额 (平台币)',
      'exchange.sell_amount': '卖出金额 (游戏币)',
      'exchange.get_quote': '询价',
      'exchange.confirm': '确认兑换',
      'exchange.quote_title': '兑换报价',
      'exchange.rate': '兑换率',
      'exchange.from_amount': '支付金额',
      'exchange.to_amount': '获得金额',
      'exchange.fee': '手续费',
      'exchange.success': '兑换成功',
      'exchange.order_no': '交易号',

      // Withdraw
      'withdraw.title': '提现',
      'withdraw.subtitle': '将平台余额提现到您的账户',
      'withdraw.amount': '提现金额',
      'withdraw.method': '提现方式',
      'withdraw.account_info': '账户信息',
      'withdraw.paypal_email': 'PayPal 邮箱地址',
      'withdraw.bank_info': '银行账户信息',
      'withdraw.crypto_address': '加密货币地址',
      'withdraw.submit': '提交提现申请',
      'withdraw.success': '提现申请已提交',
      'withdraw.limits': '最低提现: {min}  |  每日限额: {daily}',
      'withdraw.min_limit_error': '最低提现金额为 ',
      'withdraw.daily_limit_error': '超过每日提现限额 ',
      'withdraw.enter_account': '请输入收款账户信息',
      'withdraw.enter_amount': '请输入提现金额',
      'withdraw.invalid_amount': '请输入有效的金额',

      // Profile
      'profile.title': '个人中心',
      'profile.edit': '编辑资料',
      'profile.nickname': '昵称',
      'profile.avatar': '头像 URL',
      'profile.avatar_hint': '输入头像图片链接',
      'profile.language': '语言',
      'profile.language_hint': '如 zh-CN, en-US',
      'profile.username': '用户名',
      'profile.country': '国家',
      'profile.registered': '注册日期',
      'profile.account_info': '账号信息',
      'profile.logout': '退出登录',
      'profile.save_success': '保存成功',
      'profile.confirm_logout': '确认退出',
      'profile.confirm_logout_msg': '确定要退出登录吗？',

      // Login
      'login.title': '全球游戏聚合平台',
      'login.welcome': '欢迎回来',
      'login.create_account': '创建新账号',
      'login.username': '用户名',
      'login.password': '密码',
      'login.login': '登 录',
      'login.register': '注 册',
      'login.have_account': '已有账号？',
      'login.no_account': '没有账号？',
      'login.go_login': '去登录',
      'login.go_register': '去注册',
      'login.enter_credentials': '请输入用户名和密码',
      'login.operation_failed': '操作失败',
      'login.oauth_google': 'Google账号登录',
      'login.oauth_facebook': 'Facebook账号登录',
      'login.oauth_apple': 'Apple账号登录',
      'login.or': '或',
      'login.oauth_failed': 'OAuth 登录失败',
      'login.oauth_unavailable': 'OAuth 未配置',
      'login.welcome_new': '账号创建成功',

      // 2FA
      'two_factor.title': '双因素认证',
      'two_factor.verify_title': '验证双因素认证',
      'two_factor.enabled': '已启用双因素认证',
      'two_factor.disabled': '未启用双因素认证',
      'two_factor.setup': '设置双因素认证',
      'two_factor.enable': '启用',
      'two_factor.disable': '禁用',
      'two_factor.secret': '复制密钥',
      'two_factor.code': '6位验证码',
      'two_factor.password': '密码',
      'two_factor.user_id': '用户ID',
      'two_factor.backup_codes': '备用码',
      'two_factor.verify': '验证',
      'two_factor.enter_code': '请输入6位验证码',
      'two_factor.setup_hint': '请将密钥添加到验证器应用',

      // Coupon
      'coupon.title': '优惠券',
      'coupon.available': '可领取',
      'coupon.my': '我的优惠券',
      'coupon.claim': '领取',
      'coupon.claimed': '领取成功',
      'coupon.empty': '暂无优惠券',
      'coupon.value': '面额',

      // Leaderboard
      'leaderboard.title': '排行榜',
      'leaderboard.empty': '暂无排名',

      // Notification
      'notification.title': '通知',
      'notification.empty': '暂无通知',
      'notification.mark_read': '已读',
      'notification.mark_all_read': '全部已读',

      // OAuth
      'oauth.processing': '正在完成登录...',
      'oauth.failed': 'OAuth 回调失败',

      // Identity
      'identity.title': '实名认证',
      'identity.verified': '已认证',
      'identity.pending': '审核中',
      'identity.full_name': '真实姓名',
      'identity.id_type_label': '证件类型',
      'identity.id_card': '身份证',
      'identity.passport': '护照',
      'identity.driver_license': '驾驶证',
      'identity.id_number': '证件号码',
      'identity.front_photo': '证件正面照 URL',
      'identity.back_photo': '证件背面照 URL（可选）',
      'identity.selfie_photo': '手持证件照 URL',
      'identity.country': '国家（ISO 代码）',
      'identity.required': '必填',
      'identity.submit': '提交',
      'identity.submitting': '提交中...',
      'identity.submitted': '实名认证已提交',
      'identity.submit_failed': '提交失败，请重试',
      'identity.real_name': '姓名',
      'identity.type': '证件类型',
      'identity.review_note': '备注',
      'identity.rejected': '已驳回',

      // Chat
      'chat.title': '消息',
      'chat.hint': '输入消息...',
      'chat.empty': '暂无会话',
      'chat.reconnecting': '重新连接中...',

      // Friend
      'friend.tab_friends': '好友',
      'friend.tab_requests': '请求',
      'friend.tab_search': '搜索',
      'friend.search_hint': '搜索用户...',
      'friend.add': '添加',
      'friend.request_sent': '好友请求已发送',
      'friend.no_friends': '暂无好友',
      'friend.no_requests': '暂无待处理请求',
      'friend.no_results': '未找到用户',

      // Common
      'common.collapse_menu': '收起菜单',
      'common.expand_menu': '展开菜单',
      'common.platform': '游戏平台',
    },
  };

  static StringResult t(String key) {
    final locale = Get.locale?.languageCode ?? 'en';
    return StringResult(key, locale);
  }

  static String get(String key, String locale) {
    return _data[locale]?[key] ?? _data['en']?[key] ?? key;
  }
}

class StringResult {
  final String _key;
  final String _locale;
  StringResult(this._key, this._locale);

  @override
  String toString() => AppTranslations.get(_key, _locale);
}
