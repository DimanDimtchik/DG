import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'api_message.dart';

/// MA1b — Token speichern / löschen / 401 → Refresh → einmal Retry.
/// Identisch in Kalender- und Mitarbeiter-App.

const mobileTokenPrefsKey = 'token';
const mobileBaseUrlPrefsKey = 'base_url';

Future<void> mobileSaveToken(String token) async {
  final p = await SharedPreferences.getInstance();
  await p.setString(mobileTokenPrefsKey, token);
}

Future<void> mobileClearToken() async {
  final p = await SharedPreferences.getInstance();
  await p.remove(mobileTokenPrefsKey);
}

Future<String?> mobileLoadToken() async {
  final p = await SharedPreferences.getInstance();
  final t = p.getString(mobileTokenPrefsKey);
  if (t == null || t.trim().isEmpty) return null;
  return t;
}

bool apiIsUnauthorized(Map<String, dynamic> body, int statusCode) {
  if (statusCode == 401) return true;
  return '${body['code'] ?? ''}'.trim() == 'unauthorized';
}

bool apiIsAuthPath(String path) {
  return path.contains('/api/mobile/auth/');
}

/// Frischt das Bearer-Token auf (gemeinsames Lock bei parallelen 401).
Future<bool> mobileTryRefreshToken({
  required String? Function() getToken,
  required Future<void> Function(String token) setToken,
  required Future<http.Response> Function(String bearer) sendRefresh,
  Future<bool>? Function()? getRefreshLock,
  void Function(Future<bool>?)? setRefreshLock,
}) async {
  final bearer = getToken();
  if (bearer == null || bearer.isEmpty) {
    return false;
  }

  Future<bool> doRefresh() async {
    final refreshRes = await sendRefresh(bearer);
    final refreshMap = apiParseJsonBody(refreshRes.bodyBytes);
    if (!apiIsOk(refreshMap)) {
      return false;
    }
    final data = refreshMap['data'];
    final newToken = data is Map ? '${data['token'] ?? ''}'.trim() : '';
    if (newToken.isEmpty) {
      return false;
    }
    await setToken(newToken);
    await mobileSaveToken(newToken);
    return true;
  }

  final existing = getRefreshLock?.call();
  final Future<bool> refreshFuture = existing ?? doRefresh();
  if (existing == null) {
    setRefreshLock?.call(refreshFuture);
  }
  try {
    return await refreshFuture;
  } finally {
    if (existing == null) {
      setRefreshLock?.call(null);
    }
  }
}

/// Request mit optionalem Token-Refresh bei 401 (nicht für Auth-Endpunkte selbst).
Future<Map<String, dynamic>> mobileJsonWithRefresh({
  required String path,
  required String? Function() getToken,
  required Future<void> Function(String token) setToken,
  required Future<void> Function() onSessionExpired,
  required Future<http.Response> Function() send,
  required Future<http.Response> Function(String bearer) sendRefresh,
  Future<bool>? Function()? getRefreshLock,
  void Function(Future<bool>?)? setRefreshLock,
}) async {
  final first = await send();
  final firstMap = apiParseJsonBody(first.bodyBytes);
  if (!apiIsUnauthorized(firstMap, first.statusCode) || apiIsAuthPath(path)) {
    return firstMap;
  }

  final ok = await mobileTryRefreshToken(
    getToken: getToken,
    setToken: setToken,
    sendRefresh: sendRefresh,
    getRefreshLock: getRefreshLock,
    setRefreshLock: setRefreshLock,
  );
  if (!ok) {
    await mobileClearToken();
    await onSessionExpired();
    return firstMap;
  }

  final second = await send();
  return apiParseJsonBody(second.bodyBytes);
}
