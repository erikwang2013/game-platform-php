/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import 'package:shared_preferences/shared_preferences.dart';

class AuthService {
  static const _keyToken = 'access_token';
  static const _keyRefreshToken = 'refresh_token';
  static const _keyUsername = 'username';

  static String? _cachedToken;
  static String? _cachedRefreshToken;
  static String? _cachedUsername;

  static String? get cachedToken => _cachedToken;

  static Future<void> saveLogin({
    required String token,
    required String refreshToken,
    required String username,
  }) async {
    _cachedToken = token;
    _cachedRefreshToken = refreshToken;
    _cachedUsername = username;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToken, token);
    await prefs.setString(_keyRefreshToken, refreshToken);
    await prefs.setString(_keyUsername, username);
  }

  static Future<String?> getToken() async {
    if (_cachedToken != null) return _cachedToken;
    final prefs = await SharedPreferences.getInstance();
    _cachedToken = prefs.getString(_keyToken);
    return _cachedToken;
  }

  static Future<String?> getRefreshToken() async {
    if (_cachedRefreshToken != null) return _cachedRefreshToken;
    final prefs = await SharedPreferences.getInstance();
    _cachedRefreshToken = prefs.getString(_keyRefreshToken);
    return _cachedRefreshToken;
  }

  static Future<String?> getUsername() async {
    if (_cachedUsername != null) return _cachedUsername;
    final prefs = await SharedPreferences.getInstance();
    _cachedUsername = prefs.getString(_keyUsername);
    return _cachedUsername;
  }

  static Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  static Future<void> clearToken() async {
    _cachedToken = null;
    _cachedRefreshToken = null;
    _cachedUsername = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyToken);
    await prefs.remove(_keyRefreshToken);
    await prefs.remove(_keyUsername);
  }
}
