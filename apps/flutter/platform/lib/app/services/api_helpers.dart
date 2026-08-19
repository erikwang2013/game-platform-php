// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

/// Normalizes list payloads from `{ data: [...] }` or `{ data: { items|list: [...] } }`.
class ApiHelpers {
  static List<Map<String, dynamic>> extractList(dynamic data) {
    if (data == null) return const [];
    if (data is List) {
      return data.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    if (data is Map) {
      final nested = data['items'] ?? data['list'];
      if (nested is List) {
        return extractList(nested);
      }
    }
    return const [];
  }
}
