// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AuthService {
  static const _keyToken = 'access_token';
  static const _keyRefreshToken = 'refresh_token';
  static const _keyUsername = 'username';
  static const _keyOauthProvider = 'oauth_pending_provider';

  static String? _cachedToken;
  static String? _cachedRefreshToken;
  static String? _cachedUsername;

  static String? get cachedToken => _cachedToken;

  static const _secure = FlutterSecureStorage();
  static bool get _useSecureStorage => !kIsWeb;

  static Future<void> _set(String key, String value) async {
    if (_useSecureStorage) {
      await _secure.write(key: key, value: value);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(key, value);
    }
  }

  static Future<String?> _get(String key) async {
    if (_useSecureStorage) {
      return _secure.read(key: key);
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(key);
  }

  static Future<void> _remove(String key) async {
    if (_useSecureStorage) {
      await _secure.delete(key: key);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(key);
    }
  }

  static Future<void> saveLogin({
    required String token,
    required String refreshToken,
    required String username,
  }) async {
    _cachedToken = token;
    _cachedRefreshToken = refreshToken;
    _cachedUsername = username;
    await _set(_keyToken, token);
    await _set(_keyRefreshToken, refreshToken);
    await _set(_keyUsername, username);
  }

  static Future<String?> getToken() async {
    if (_cachedToken != null) return _cachedToken;
    _cachedToken = await _get(_keyToken);
    return _cachedToken;
  }

  static Future<String?> getRefreshToken() async {
    if (_cachedRefreshToken != null) return _cachedRefreshToken;
    _cachedRefreshToken = await _get(_keyRefreshToken);
    return _cachedRefreshToken;
  }

  static Future<String?> getUsername() async {
    if (_cachedUsername != null) return _cachedUsername;
    _cachedUsername = await _get(_keyUsername);
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
    await _remove(_keyToken);
    await _remove(_keyRefreshToken);
    await _remove(_keyUsername);
  }

  static Future<void> setPendingOauthProvider(String provider) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyOauthProvider, provider);
  }

  static Future<String?> takePendingOauthProvider() async {
    final prefs = await SharedPreferences.getInstance();
    final value = prefs.getString(_keyOauthProvider);
    await prefs.remove(_keyOauthProvider);
    return value;
  }
}
