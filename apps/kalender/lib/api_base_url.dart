import 'package:shared_preferences/shared_preferences.dart';

import 'api_auth.dart';

/// MA1c — CRM-Basis-URL: Validierung, Speichern, Connect-/Deep-Link erkennen.
/// Identisch in Kalender- und Mitarbeiter-App.
///
/// Kein QR-Kamera-Plugin in den Apps — Nutzer können den Link von `/app-connect`
/// (oder den Deep-Link) einfügen; die Adresse wird daraus gelesen.

/// Strikte Normalisierung: https ergänzen, Slash am Ende weg, Host prüfen.
String normalizeBaseUrl(String raw) {
  var u = raw.trim();
  if (u.isEmpty) {
    throw ArgumentError('CRM-Adresse fehlt.');
  }
  if (!u.contains('://')) {
    u = 'https://$u';
  }
  u = u.replaceAll(RegExp(r'/+$'), '');
  final parsed = Uri.tryParse(u);
  if (parsed == null || !parsed.hasScheme || parsed.host.isEmpty) {
    throw ArgumentError('Ungültige CRM-Adresse. Beispiel: https://firma.example');
  }
  if (parsed.scheme != 'http' && parsed.scheme != 'https') {
    throw ArgumentError('CRM-Adresse muss mit http:// oder https:// beginnen.');
  }
  return '${parsed.scheme}://${parsed.host}${parsed.hasPort ? ':${parsed.port}' : ''}';
}

/// Eingabe aus Tippen, Zwischenablage, Deep-Link oder `/app-connect?base=…` → Basis-URL.
String resolveCrmBaseUrl(String raw) {
  final trimmed = raw.trim();
  if (trimmed.isEmpty) {
    throw ArgumentError('CRM-Adresse fehlt.');
  }

  final uri = Uri.tryParse(trimmed);
  if (uri != null) {
    final baseParam = uri.queryParameters['base'];
    if (baseParam != null && baseParam.trim().isNotEmpty) {
      return normalizeBaseUrl(baseParam);
    }

    // Deep-Link z. B. dg-mitarbeiter://setup?base=… (base oben schon geprüft)
    if (uri.scheme.startsWith('dg-')) {
      throw ArgumentError(
        'Deep-Link ohne CRM-Adresse. Bitte die Verbindungsseite erneut öffnen.',
      );
    }

    if ((uri.scheme == 'http' || uri.scheme == 'https') && uri.host.isNotEmpty) {
      // Beliebige CRM-Web-URL (app-connect, app-download, Startseite) → Origin
      return normalizeBaseUrl(
        '${uri.scheme}://${uri.host}${uri.hasPort ? ':${uri.port}' : ''}',
      );
    }
  }

  return normalizeBaseUrl(trimmed);
}

Future<void> mobileSaveBaseUrl(String baseUrl) async {
  final normalized = normalizeBaseUrl(baseUrl);
  final p = await SharedPreferences.getInstance();
  await p.setString(mobileBaseUrlPrefsKey, normalized);
}

Future<String?> mobileLoadBaseUrl() async {
  final p = await SharedPreferences.getInstance();
  final raw = p.getString(mobileBaseUrlPrefsKey);
  if (raw == null || raw.trim().isEmpty) return null;
  try {
    return normalizeBaseUrl(raw);
  } catch (_) {
    await p.remove(mobileBaseUrlPrefsKey);
    return null;
  }
}
