import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const Color _brandTeal = Color(0xFF0F766E);
const Color _brandTealDark = Color(0xFF115E59);
const Color _surfaceMist = Color(0xFFF3F7F6);

void main() {
  runApp(const DgKalenderApp());
}

/// CRM-Basis-URL normalisieren (https:// ergänzen, Slash am Ende entfernen).
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
    throw ArgumentError('Ungültige CRM-Adresse. Beispiel: https://dg.ganz-om.de');
  }
  return u;
}

String formatSlotTime(String slot) {
  final m = RegExp(r'(\d{2}):(\d{2})').firstMatch(slot);
  if (m != null) return '${m.group(1)}:${m.group(2)}';
  return slot;
}

String formatSlotFull(String slot) {
  final m = RegExp(r'(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})').firstMatch(slot);
  if (m != null) {
    return '${m.group(3)}.${m.group(2)}.${m.group(1)} · ${m.group(4)}:${m.group(5)} Uhr';
  }
  return slot;
}

String formatDayChip(DateTime d) {
  const wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
  return '${wd[d.weekday - 1]}\n${d.day}.${d.month}.';
}

String ymd(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

ThemeData buildKalenderTheme() {
  final scheme = ColorScheme.fromSeed(
    seedColor: _brandTeal,
    brightness: Brightness.light,
    primary: _brandTeal,
    secondary: _brandTealDark,
    surface: Colors.white,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: _surfaceMist,
    appBarTheme: const AppBarTheme(
      centerTitle: false,
      elevation: 0,
      scrolledUnderElevation: 0,
      backgroundColor: _surfaceMist,
      foregroundColor: Color(0xFF134E4A),
      titleTextStyle: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.3,
        color: Color(0xFF134E4A),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 72,
      elevation: 0,
      backgroundColor: Colors.white,
      indicatorColor: _brandTeal.withValues(alpha: 0.14),
      labelTextStyle: WidgetStateProperty.resolveWith((states) {
        final selected = states.contains(WidgetState.selected);
        return TextStyle(
          fontSize: 12,
          fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          color: selected ? _brandTealDark : const Color(0xFF64748B),
        );
      }),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      margin: EdgeInsets.zero,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFD1E4E0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Color(0xFFD1E4E0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _brandTeal, width: 1.6),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: _brandTeal,
        foregroundColor: Colors.white,
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    dialogTheme: DialogThemeData(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
  );
}

class DgKalenderApp extends StatelessWidget {
  const DgKalenderApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'DG Kalender',
      theme: buildKalenderTheme(),
      home: const BootstrapPage(),
    );
  }
}

class ApiClient {
  ApiClient(String baseUrl, {this.token}) : baseUrl = normalizeBaseUrl(baseUrl);

  final String baseUrl;
  String? token;

  Uri _u(String path, [Map<String, String>? q]) {
    final base = Uri.parse(baseUrl);
    final uri = base.replace(path: '${base.path.replaceAll(RegExp(r'/+$'), '')}$path');
    if (q == null || q.isEmpty) return uri;
    return uri.replace(queryParameters: q);
  }

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) async {
    final res = await http.post(
      _u(path),
      headers: {
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      },
      body: jsonEncode(body),
    );
    return jsonDecode(utf8.decode(res.bodyBytes)) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> get(String path, [Map<String, String>? q]) async {
    final res = await http.get(
      _u(path, q),
      headers: {if (token != null) 'Authorization': 'Bearer $token'},
    );
    return jsonDecode(utf8.decode(res.bodyBytes)) as Map<String, dynamic>;
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.detail,
  });

  final IconData icon;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: cs.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Icon(icon, size: 34, color: cs.primary),
            ),
            const SizedBox(height: 20),
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF134E4A),
                  ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              detail,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: const Color(0xFF64748B),
                    height: 1.4,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class BrandHero extends StatelessWidget {
  const BrandHero({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0F766E), Color(0xFF134E4A)],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(999),
              ),
              child: const Text(
                'DG Kalender',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                  letterSpacing: 0.2,
                ),
              ),
            ),
            const SizedBox(height: 14),
            Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 28,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.6,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.85),
                fontSize: 15,
                height: 1.35,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class BootstrapPage extends StatefulWidget {
  const BootstrapPage({super.key});

  @override
  State<BootstrapPage> createState() => _BootstrapPageState();
}

class _BootstrapPageState extends State<BootstrapPage> {
  final _url = TextEditingController();
  String? _token;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final p = await SharedPreferences.getInstance();
    var saved = p.getString('base_url') ?? '';
    _token = p.getString('token');
    if (saved.isNotEmpty) {
      try {
        saved = normalizeBaseUrl(saved);
        await p.setString('base_url', saved);
      } catch (_) {
        saved = '';
        await p.remove('base_url');
      }
    }
    _url.text = saved;
    setState(() => _loading = false);
    if (saved.isNotEmpty && _token != null && _token!.isNotEmpty) {
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => HomePage(client: ApiClient(saved, token: _token)),
        ),
      );
    }
  }

  Future<void> _saveUrl() async {
    try {
      final normalized = normalizeBaseUrl(_url.text);
      final p = await SharedPreferences.getInstance();
      await p.setString('base_url', normalized);
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => LoginPage(baseUrl: normalized),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Invalid argument(s): ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return Scaffold(
      body: Column(
        children: [
          const BrandHero(
            title: 'Willkommen',
            subtitle: 'Verbinden Sie die App mit Ihrem CRM, um Termine zu buchen.',
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
              children: [
                Text(
                  'CRM-Adresse',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF134E4A),
                      ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _url,
                  decoration: const InputDecoration(
                    hintText: 'https://dg.ganz-om.de',
                    prefixIcon: Icon(Icons.language_rounded),
                  ),
                  keyboardType: TextInputType.url,
                ),
                const SizedBox(height: 8),
                Text(
                  'Ohne Slash am Ende. Beispiel: https://dg.ganz-om.de',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: const Color(0xFF64748B),
                      ),
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _saveUrl,
                  child: const Text('Weiter zur Anmeldung'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.baseUrl});
  final String baseUrl;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _name = TextEditingController();
  bool _register = false;
  String? _error;
  bool _busy = false;

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final client = ApiClient(widget.baseUrl);
      final path = _register
          ? '/api/mobile/auth/customer/register'
          : '/api/mobile/auth/customer/login';
      final body = <String, dynamic>{
        'email': _email.text.trim(),
        'password': _password.text,
        if (_register) 'name': _name.text.trim(),
      };
      final res = await client.post(path, body);
      if (res['ok'] != true) {
        setState(() => _error = (res['error'] ?? 'Fehler').toString());
        return;
      }
      final data = res['data'] as Map<String, dynamic>;
      final token = data['token'] as String;
      final p = await SharedPreferences.getInstance();
      await p.setString('base_url', client.baseUrl);
      await p.setString('token', token);
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => HomePage(client: ApiClient(client.baseUrl, token: token)),
        ),
        (_) => false,
      );
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Invalid argument(s): ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          BrandHero(
            title: _register ? 'Konto anlegen' : 'Anmelden',
            subtitle: _register
                ? 'Einmal registrieren, dann Termine direkt in der App buchen.'
                : 'Melden Sie sich mit Ihrer Kunden-E-Mail an.',
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
              children: [
                if (_register) ...[
                  TextField(
                    controller: _name,
                    decoration: const InputDecoration(
                      labelText: 'Name',
                      prefixIcon: Icon(Icons.person_outline_rounded),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                TextField(
                  controller: _email,
                  decoration: const InputDecoration(
                    labelText: 'E-Mail',
                    prefixIcon: Icon(Icons.mail_outline_rounded),
                  ),
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  decoration: const InputDecoration(
                    labelText: 'Passwort',
                    prefixIcon: Icon(Icons.lock_outline_rounded),
                  ),
                  obscureText: true,
                ),
                if (_error != null) ...[
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.errorContainer,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      _error!,
                      style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(_register ? 'Konto anlegen' : 'Anmelden'),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => setState(() => _register = !_register),
                  child: Text(_register ? 'Bereits Konto? Anmelden' : 'Neu hier? Registrieren'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class HomePage extends StatefulWidget {
  const HomePage({super.key, required this.client});
  final ApiClient client;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  int _tab = 0;
  List<dynamic> _bookings = [];
  List<dynamic> _articles = [];
  String? _bookingsError;
  String? _catalogError;
  bool _loading = true;
  int? _cancellingId;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Map<String, dynamic>? _dataMap(Map<String, dynamic> res) {
    final d = res['data'];
    return d is Map<String, dynamic> ? d : null;
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _bookingsError = null;
      _catalogError = null;
    });
    try {
      final b = await widget.client.get('/api/mobile/customer/bookings');
      final c = await widget.client.get('/api/mobile/customer/catalog');
      if (!mounted) return;
      final bData = _dataMap(b);
      final cData = _dataMap(c);
      setState(() {
        _bookings = (bData?['bookings'] as List?) ?? [];
        _articles = (cData?['articles'] as List?) ?? [];
        _bookingsError =
            b['ok'] == true ? null : (b['error'] ?? 'Termine konnten nicht geladen werden.').toString();
        _catalogError = c['ok'] == true
            ? null
            : (c['error'] ?? 'Katalog konnte nicht geladen werden.').toString();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _bookingsError = e.toString();
        _catalogError = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _logout() async {
    try {
      await widget.client.post('/api/mobile/auth/logout', {});
    } catch (_) {}
    final p = await SharedPreferences.getInstance();
    await p.remove('token');
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const BootstrapPage()),
      (_) => false,
    );
  }

  Future<void> _openBooking(Map<String, dynamic> a) async {
    final booked = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => BookSlotPage(client: widget.client, article: a),
      ),
    );
    if (!mounted) return;
    if (booked == true) {
      await _reload();
      setState(() => _tab = 0);
    }
  }

  bool _isCancellable(Map<String, dynamic> b) {
    final status = '${b['status'] ?? ''}'.toLowerCase();
    if (status.contains('storn') || status.contains('cancel')) return false;
    final id = b['id'];
    return id is int || (id != null && int.tryParse('$id') != null);
  }

  Future<void> _cancelBooking(Map<String, dynamic> b) async {
    final id = int.tryParse('${b['id']}') ?? 0;
    if (id < 1 || _cancellingId != null) return;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Termin stornieren?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(formatSlotFull('${b['slot_datetime']}')),
            if ('${b['booking_code'] ?? ''}'.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(
                'Code: ${b['booking_code']}',
                style: const TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w600),
              ),
            ],
            const SizedBox(height: 14),
            Text(
              'Der Termin wird storniert und der Slot wieder freigegeben.',
              style: TextStyle(color: Colors.grey.shade700, height: 1.35),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFB91C1C),
              minimumSize: const Size(0, 44),
            ),
            child: const Text('Stornieren'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _cancellingId = id);
    try {
      final res = await widget.client.post('/api/mobile/customer/bookings/$id/cancel', {});
      if (!mounted) return;
      if (res['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Termin storniert.')),
        );
        await _reload();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['error'] ?? 'Storno fehlgeschlagen').toString())),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _cancellingId = null);
    }
  }

  Widget _bookingsTab() {
    if (_bookingsError != null) {
      return EmptyState(icon: Icons.cloud_off_rounded, title: 'Termine nicht ladbar', detail: _bookingsError!);
    }
    if (_bookings.isEmpty) {
      return const EmptyState(
        icon: Icons.event_available_rounded,
        title: 'Noch keine Termine',
        detail: 'Unter „Buchen“ eine Leistung wählen und eine Uhrzeit bestätigen.',
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: _bookings.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, i) {
        final b = _bookings[i] as Map<String, dynamic>;
        final status = '${b['status_label'] ?? b['status']}';
        final code = '${b['booking_code'] ?? ''}';
        final id = int.tryParse('${b['id']}') ?? 0;
        final canCancel = _isCancellable(b);
        final cancelling = _cancellingId == id;
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: _brandTeal.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.event_rounded, color: _brandTeal),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            formatSlotFull('${b['slot_datetime']}'),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 16,
                              color: Color(0xFF134E4A),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _StatusChip(label: status),
                              if (code.isNotEmpty)
                                Text(
                                  code,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF64748B),
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (canCancel) ...[
                  const SizedBox(height: 14),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: (_cancellingId != null) ? null : () => _cancelBooking(b),
                      icon: cancelling
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.event_busy_rounded, size: 18),
                      label: Text(cancelling ? 'Storniere …' : 'Stornieren'),
                      style: TextButton.styleFrom(
                        foregroundColor: const Color(0xFFB91C1C),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _catalogTab() {
    if (_catalogError != null) {
      return EmptyState(icon: Icons.cloud_off_rounded, title: 'Buchen nicht möglich', detail: _catalogError!);
    }
    if (_articles.isEmpty) {
      return const EmptyState(
        icon: Icons.spa_rounded,
        title: 'Keine buchbaren Leistungen',
        detail: 'Im CRM: Online-Buchung aktivieren und Leistungen anlegen.',
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: _articles.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, i) {
        final a = _articles[i] as Map<String, dynamic>;
        final price = '${a['price_label'] ?? ''}'.trim();
        return Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => _openBooking(a),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0xFF14B8A6), Color(0xFF0F766E)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Icon(Icons.spa_rounded, color: Colors.white),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${a['title']}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 17,
                            color: Color(0xFF134E4A),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${a['duration_minutes']} Min.${price.isNotEmpty ? '  ·  $price' : ''}',
                          style: const TextStyle(color: Color(0xFF64748B), fontSize: 14),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: _surfaceMist,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.arrow_forward_rounded, color: _brandTeal, size: 20),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_tab == 0 ? 'Meine Termine' : 'Termin buchen'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _reload,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Aktualisieren',
          ),
          IconButton(
            onPressed: _logout,
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Abmelden',
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : (_tab == 0 ? _bookingsTab() : _catalogTab()),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.event_outlined),
            selectedIcon: Icon(Icons.event_rounded),
            label: 'Termine',
          ),
          NavigationDestination(
            icon: Icon(Icons.add_circle_outline),
            selectedIcon: Icon(Icons.add_circle_rounded),
            label: 'Buchen',
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final booked = label.toLowerCase().contains('gebucht');
    final color = booked ? _brandTeal : const Color(0xFF64748B);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12),
      ),
    );
  }
}

/// Terminwahl: Datum + Slot wählen, erst nach Bestätigung buchen.
class BookSlotPage extends StatefulWidget {
  const BookSlotPage({super.key, required this.client, required this.article});

  final ApiClient client;
  final Map<String, dynamic> article;

  @override
  State<BookSlotPage> createState() => _BookSlotPageState();
}

class _BookSlotPageState extends State<BookSlotPage> {
  late DateTime _selectedDay;
  List<String> _slots = [];
  String? _error;
  bool _loadingSlots = true;
  bool _bookingBusy = false;

  @override
  void initState() {
    super.initState();
    _selectedDay = DateTime.now().add(const Duration(days: 1));
    _loadSlots();
  }

  Map<String, dynamic>? _dataMap(Map<String, dynamic> res) {
    final d = res['data'];
    return d is Map<String, dynamic> ? d : null;
  }

  Future<void> _loadSlots() async {
    setState(() {
      _loadingSlots = true;
      _error = null;
      _slots = [];
    });
    try {
      final res = await widget.client.get('/api/mobile/customer/slots', {
        'article_id': '${widget.article['id']}',
        'date': ymd(_selectedDay),
      });
      if (!mounted) return;
      if (res['ok'] != true) {
        setState(() {
          _error = (res['error'] ?? 'Zeiten nicht ladbar').toString();
          _loadingSlots = false;
        });
        return;
      }
      final raw = (_dataMap(res)?['slots'] as List?) ?? [];
      setState(() {
        _slots = raw.map((e) => e.toString()).toList();
        _loadingSlots = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingSlots = false;
      });
    }
  }

  Future<void> _confirmAndBook(String slot) async {
    if (_bookingBusy) return;
    final title = '${widget.article['title']}';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Termin verbindlich buchen?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(height: 8),
            Text(formatSlotFull(slot)),
            const SizedBox(height: 14),
            Text(
              'Erst nach „Jetzt buchen“ wird der Termin angelegt.',
              style: TextStyle(color: Colors.grey.shade700, height: 1.35),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
            child: const Text('Jetzt buchen'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _bookingBusy = true);
    try {
      final book = await widget.client.post('/api/mobile/customer/bookings', {
        'article_id': widget.article['id'],
        'slot_datetime': slot,
      });
      if (!mounted) return;
      if (book['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Termin gebucht: ${formatSlotFull(slot)}')),
        );
        Navigator.of(context).pop(true);
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text((book['error'] ?? 'Buchung fehlgeschlagen').toString())),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _bookingBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final days = List.generate(14, (i) => DateTime.now().add(Duration(days: i)));
    final articleTitle = '${widget.article['title']}';
    final price = '${widget.article['price_label'] ?? ''}'.trim();
    final subtitle =
        '${widget.article['duration_minutes']} Min.${price.isNotEmpty ? '  ·  $price' : ''}';

    return Scaffold(
      appBar: AppBar(title: const Text('Zeit wählen')),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: _brandTeal.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.spa_rounded, color: _brandTeal),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            articleTitle,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 18,
                              color: Color(0xFF134E4A),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(subtitle, style: const TextStyle(color: Color(0xFF64748B))),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Text(
              'Datum',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF134E4A),
                  ),
            ),
          ),
          SizedBox(
            height: 78,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: days.length,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (_, i) {
                final d = days[i];
                final selected = ymd(d) == ymd(_selectedDay);
                return Material(
                  color: selected ? _brandTeal : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: _bookingBusy
                        ? null
                        : () {
                            setState(() => _selectedDay = d);
                            _loadSlots();
                          },
                    child: Container(
                      width: 64,
                      alignment: Alignment.center,
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      child: Text(
                        formatDayChip(d),
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          height: 1.25,
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          color: selected ? Colors.white : const Color(0xFF334155),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Text(
              'Uhrzeit',
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF134E4A),
                  ),
            ),
          ),
          Expanded(
            child: _loadingSlots
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? EmptyState(icon: Icons.cloud_off_rounded, title: 'Zeiten nicht ladbar', detail: _error!)
                    : _slots.isEmpty
                        ? const EmptyState(
                            icon: Icons.schedule_rounded,
                            title: 'Keine freien Zeiten',
                            detail: 'An diesem Tag ist nichts verfügbar. Bitte anderes Datum wählen.',
                          )
                        : GridView.builder(
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 3,
                              mainAxisSpacing: 10,
                              crossAxisSpacing: 10,
                              childAspectRatio: 2.1,
                            ),
                            itemCount: _slots.length,
                            itemBuilder: (_, i) {
                              final slot = _slots[i];
                              return Material(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(14),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(14),
                                  onTap: _bookingBusy ? null : () => _confirmAndBook(slot),
                                  child: Container(
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(color: const Color(0xFFD1E4E0)),
                                    ),
                                    child: _bookingBusy
                                        ? const SizedBox(
                                            width: 18,
                                            height: 18,
                                            child: CircularProgressIndicator(strokeWidth: 2),
                                          )
                                        : Text(
                                            formatSlotTime(slot),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w700,
                                              fontSize: 16,
                                              color: Color(0xFF134E4A),
                                            ),
                                          ),
                                  ),
                                ),
                              );
                            },
                          ),
          ),
        ],
      ),
    );
  }
}
