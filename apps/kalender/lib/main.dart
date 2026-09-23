import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

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

class DgKalenderApp extends StatelessWidget {
  const DgKalenderApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'DG Kalender',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0F766E)),
        useMaterial3: true,
      ),
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
      appBar: AppBar(title: const Text('DG Kalender — Setup')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('CRM-Adresse Ihrer Firma (ohne Slash am Ende):'),
            const SizedBox(height: 8),
            TextField(
              controller: _url,
              decoration: const InputDecoration(
                hintText: 'https://dg.ganz-om.de',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: _saveUrl, child: const Text('Weiter')),
          ],
        ),
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
      appBar: AppBar(title: Text(_register ? 'Registrieren' : 'Anmelden')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          if (_register)
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Name'),
            ),
          TextField(
            controller: _email,
            decoration: const InputDecoration(labelText: 'E-Mail'),
            keyboardType: TextInputType.emailAddress,
          ),
          TextField(
            controller: _password,
            decoration: const InputDecoration(labelText: 'Passwort'),
            obscureText: true,
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _busy ? null : _submit,
            child: Text(_busy ? '…' : (_register ? 'Konto anlegen' : 'Login')),
          ),
          TextButton(
            onPressed: () => setState(() => _register = !_register),
            child: Text(_register ? 'Bereits Konto? Anmelden' : 'Neu? Registrieren'),
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
        _bookingsError = b['ok'] == true ? null : (b['error'] ?? 'Termine konnten nicht geladen werden.').toString();
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

  Widget _emptyBox(String title, String detail) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text(detail, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }

  Widget _bookingsTab() {
    if (_bookingsError != null) {
      return _emptyBox('Termine nicht ladbar', _bookingsError!);
    }
    if (_bookings.isEmpty) {
      return _emptyBox('Noch keine Termine', 'Unter „Buchen“ eine Leistung wählen.');
    }
    return ListView.builder(
      itemCount: _bookings.length,
      itemBuilder: (_, i) {
        final b = _bookings[i] as Map<String, dynamic>;
        return ListTile(
          title: Text('${b['slot_datetime']}'),
          subtitle: Text('${b['status_label'] ?? b['status']} · ${b['booking_code']}'),
        );
      },
    );
  }

  Widget _catalogTab() {
    if (_catalogError != null) {
      return _emptyBox('Buchen nicht möglich', _catalogError!);
    }
    if (_articles.isEmpty) {
      return _emptyBox(
        'Keine buchbaren Leistungen',
        'Im CRM: Online-Buchung aktivieren und Leistungen (Kalender-Artikel) anlegen.',
      );
    }
    return ListView.builder(
      itemCount: _articles.length,
      itemBuilder: (_, i) {
        final a = _articles[i] as Map<String, dynamic>;
        return ListTile(
          title: Text('${a['title']}'),
          subtitle: Text('${a['duration_minutes']} min · ${a['price_label'] ?? ''}'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => _openBooking(a),
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
          IconButton(onPressed: _loading ? null : _reload, icon: const Icon(Icons.refresh)),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : (_tab == 0 ? _bookingsTab() : _catalogTab()),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.event), label: 'Termine'),
          NavigationDestination(icon: Icon(Icons.add_circle), label: 'Buchen'),
        ],
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

  String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _dayLabel(DateTime d) {
    const wd = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    return '${wd[d.weekday - 1]} ${d.day}.${d.month}.';
  }

  String _slotLabel(String slot) {
    final m = RegExp(r'(\d{2}):(\d{2})').firstMatch(slot);
    if (m != null) return '${m.group(1)}:${m.group(2)}';
    return slot;
  }

  String _slotFullLabel(String slot) {
    final m = RegExp(r'(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})').firstMatch(slot);
    if (m != null) {
      return '${m.group(3)}.${m.group(2)}.${m.group(1)} um ${m.group(4)}:${m.group(5)} Uhr';
    }
    return slot;
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
        'date': _ymd(_selectedDay),
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
        content: Text(
          '$title\n${_slotFullLabel(slot)}\n\n'
          'Erst nach „Jetzt buchen“ wird der Termin angelegt.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Abbrechen')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Jetzt buchen')),
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
          SnackBar(content: Text('Termin gebucht: ${_slotFullLabel(slot)}')),
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
    final subtitle =
        '${widget.article['duration_minutes']} min · ${widget.article['price_label'] ?? ''}';

    return Scaffold(
      appBar: AppBar(title: const Text('Zeit wählen')),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(articleTitle, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(subtitle, style: Theme.of(context).textTheme.bodyMedium),
                const SizedBox(height: 8),
                Text(
                  'Bitte Datum und Uhrzeit wählen. Es wird nichts automatisch gebucht.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          SizedBox(
            height: 48,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: days.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final d = days[i];
                final selected = _ymd(d) == _ymd(_selectedDay);
                return ChoiceChip(
                  label: Text(_dayLabel(d)),
                  selected: selected,
                  onSelected: _bookingBusy
                      ? null
                      : (_) {
                          setState(() => _selectedDay = d);
                          _loadSlots();
                        },
                );
              },
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: _loadingSlots
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!)))
                    : _slots.isEmpty
                        ? const Center(
                            child: Padding(
                              padding: EdgeInsets.all(24),
                              child: Text('Keine freien Zeiten an diesem Tag.'),
                            ),
                          )
                        : ListView.builder(
                            itemCount: _slots.length,
                            itemBuilder: (_, i) {
                              final slot = _slots[i];
                              return ListTile(
                                leading: const Icon(Icons.schedule),
                                title: Text(_slotLabel(slot)),
                                subtitle: Text(_slotFullLabel(slot)),
                                trailing: _bookingBusy
                                    ? const SizedBox(
                                        width: 20,
                                        height: 20,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : const Icon(Icons.chevron_right),
                                onTap: _bookingBusy ? null : () => _confirmAndBook(slot),
                              );
                            },
                          ),
          ),
        ],
      ),
    );
  }
}
