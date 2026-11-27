import 'dart:convert';

/// Mobile-API Fehlerformat (MA1a) + Netzhinweis (MA1d) — identisch in beiden Apps.
///
/// Erfolg: `{ "ok": true, "data": {…} }`
/// Fehler: `{ "ok": false, "error": "Menschliche Meldung", "code": "snake_case" }`

const apiNetworkErrorMessage =
    'Keine Verbindung zum Server. Bitte Internet prüfen und erneut versuchen.';

bool apiIsOk(Map<String, dynamic>? res) => res != null && res['ok'] == true;

Map<String, dynamic> apiFailBody(String error, String code) => {
      'ok': false,
      'error': error,
      'code': code,
    };

/// Erkennung typischer Offline-/Netzwerk-Fehler (ohne dart:io — auch Web).
bool apiIsNetworkException(Object error) {
  final type = error.runtimeType.toString().toLowerCase();
  if (type.contains('socketexception') ||
      type.contains('handshakeexception') ||
      type.contains('clientexception') ||
      type.contains('timeoutexception') ||
      type.contains('tlsException'.toLowerCase())) {
    return true;
  }
  final s = error.toString().toLowerCase();
  return s.contains('socketexception') ||
      s.contains('failed host lookup') ||
      s.contains('network is unreachable') ||
      s.contains('connection refused') ||
      s.contains('connection reset') ||
      s.contains('connection timed out') ||
      s.contains('timed out') ||
      s.contains('timeout') ||
      s.contains('clientexception') ||
      s.contains('handshakeexception') ||
      s.contains('network error') ||
      s.contains('xmlhttprequest error') ||
      s.contains('failed to fetch');
}

/// Bekannte API-`code`-Werte → deutsche Fallback-Meldung (wenn `error` leer).
String? apiCodeMessage(String code) {
  switch (code.trim()) {
    case 'invalid_credentials':
      return 'Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen.';
    case 'unauthorized':
      return 'Sitzung abgelaufen. Bitte erneut anmelden.';
    case 'forbidden':
      return 'Keine Berechtigung für diese Aktion.';
    case 'not_found':
      return 'Eintrag nicht gefunden.';
    case 'bad_request':
      return 'Angaben unvollständig oder ungültig.';
    case 'method_not_allowed':
      return 'Aktion so nicht erlaubt.';
    case 'db_unavailable':
      return 'Dienst vorübergehend nicht verfügbar.';
    case 'server_error':
      return 'Serverfehler. Bitte später erneut versuchen.';
    case 'bad_response':
      return 'Unerwartete Antwort vom Server.';
    case 'rate_limited':
      return 'Zu viele Versuche. Bitte kurz warten.';
    case 'network_error':
    case 'offline':
      return apiNetworkErrorMessage;
    default:
      return null;
  }
}

String _sanitizeException(String raw) {
  var s = raw.trim();
  for (final prefix in const [
    'Invalid argument(s): ',
    'Exception: ',
    'FormatException: ',
    'HttpException: ',
  ]) {
    if (s.startsWith(prefix)) {
      s = s.substring(prefix.length).trim();
    }
  }
  if (s.isEmpty) {
    return 'Etwas ist schiefgelaufen. Bitte erneut versuchen.';
  }
  return s;
}

/// Menschliche Meldung aus API-Map, Exception oder String.
String apiUserMessage(
  Object? source, {
  String fallback = 'Etwas ist schiefgelaufen. Bitte erneut versuchen.',
}) {
  if (source == null) {
    return fallback;
  }
  if (source is Map) {
    final error = '${source['error'] ?? ''}'.trim();
    if (error.isNotEmpty) {
      return error;
    }
    final code = '${source['code'] ?? ''}'.trim();
    if (code.isNotEmpty) {
      return apiCodeMessage(code) ?? fallback;
    }
    if (source['ok'] == true) {
      return '';
    }
    return fallback;
  }
  if (apiIsNetworkException(source)) {
    return apiNetworkErrorMessage;
  }
  if (source is String) {
    final t = source.trim();
    if (t.isEmpty) return fallback;
    if (apiIsNetworkException(t)) return apiNetworkErrorMessage;
    return _sanitizeException(t);
  }
  return _sanitizeException(source.toString());
}

/// JSON-Body der Mobile-API lesen; bei Müll → Fail-Map (`ok`/`error`/`code`).
Map<String, dynamic> apiParseJsonBody(List<int> bytes) {
  try {
    final decoded = jsonDecode(utf8.decode(bytes));
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    if (decoded is Map) {
      return Map<String, dynamic>.from(decoded);
    }
  } catch (_) {
    // fall through
  }
  return apiFailBody('Unerwartete Antwort vom Server.', 'bad_response');
}

Map<String, dynamic> apiNetworkFailBody() =>
    apiFailBody(apiNetworkErrorMessage, 'network_error');
