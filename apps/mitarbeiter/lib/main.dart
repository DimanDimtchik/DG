import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  runApp(const DgMitarbeiterApp());
}

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

class DgMitarbeiterApp extends StatelessWidget {
  const DgMitarbeiterApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'DG Mitarbeiter',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1E3A5F)),
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

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) async {
    final res = await http.post(_u(path), headers: _headers, body: jsonEncode(body));
    return jsonDecode(utf8.decode(res.bodyBytes)) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> get(String path, [Map<String, String>? q]) async {
    final res = await http.get(_u(path, q), headers: {
      if (token != null) 'Authorization': 'Bearer $token',
    });
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
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final p = await SharedPreferences.getInstance();
    var saved = p.getString('base_url') ?? '';
    final token = p.getString('token');
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
    if (saved.isNotEmpty && token != null && token.isNotEmpty) {
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => ShellPage(client: ApiClient(saved, token: token)),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return Scaffold(
      appBar: AppBar(title: const Text('DG Mitarbeiter — Setup')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _url,
              decoration: const InputDecoration(
                labelText: 'CRM-Adresse',
                hintText: 'https://dg.ganz-om.de',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () async {
                try {
                  final normalized = normalizeBaseUrl(_url.text);
                  final p = await SharedPreferences.getInstance();
                  await p.setString('base_url', normalized);
                  if (!context.mounted) return;
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => LoginPage(baseUrl: normalized)),
                  );
                } catch (e) {
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        e.toString().replaceFirst('Invalid argument(s): ', ''),
                      ),
                    ),
                  );
                }
              },
              child: const Text('Weiter'),
            ),
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
  final _id = TextEditingController();
  final _pin = TextEditingController();
  String? _error;
  bool _busy = false;

  Future<void> _login() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final client = ApiClient(widget.baseUrl);
      final res = await client.post('/api/mobile/auth/staff/login', {
        'identifier': _id.text.trim(),
        'pin': _pin.text.trim(),
      });
      if (res['ok'] != true) {
        setState(() => _error = (res['error'] ?? 'Fehler').toString());
        return;
      }
      final token = (res['data'] as Map)['token'] as String;
      final p = await SharedPreferences.getInstance();
      await p.setString('base_url', client.baseUrl);
      await p.setString('token', token);
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => ShellPage(client: ApiClient(client.baseUrl, token: token)),
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
      appBar: AppBar(title: const Text('Anmelden')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          TextField(
            controller: _id,
            decoration: const InputDecoration(
              labelText: 'Login / E-Mail / Nachname / Nr.',
            ),
          ),
          TextField(
            controller: _pin,
            decoration: const InputDecoration(labelText: 'PIN'),
            obscureText: true,
            keyboardType: TextInputType.number,
          ),
          if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _login, child: Text(_busy ? '…' : 'Login')),
        ],
      ),
    );
  }
}

class ShellPage extends StatefulWidget {
  const ShellPage({super.key, required this.client});
  final ApiClient client;
  @override
  State<ShellPage> createState() => _ShellPageState();
}

class _ShellPageState extends State<ShellPage> {
  int _tab = 0;

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

  @override
  Widget build(BuildContext context) {
    final pages = [
      ClockTab(client: widget.client),
      KontoTab(client: widget.client),
      AbsencesTab(client: widget.client),
      ShiftsTab(client: widget.client),
      DocsTab(client: widget.client),
      ProfileTab(client: widget.client),
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text('DG Mitarbeiter'),
        actions: [IconButton(onPressed: _logout, icon: const Icon(Icons.logout))],
      ),
      body: pages[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.fingerprint), label: 'Stempel'),
          NavigationDestination(icon: Icon(Icons.account_balance_wallet), label: 'Konto'),
          NavigationDestination(icon: Icon(Icons.beach_access), label: 'Abwesend'),
          NavigationDestination(icon: Icon(Icons.calendar_view_week), label: 'Schicht'),
          NavigationDestination(icon: Icon(Icons.folder), label: 'Akte'),
          NavigationDestination(icon: Icon(Icons.person), label: 'Profil'),
        ],
      ),
    );
  }
}

class ClockTab extends StatefulWidget {
  const ClockTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<ClockTab> createState() => _ClockTabState();
}

class _ClockTabState extends State<ClockTab> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await widget.client.get('/api/mobile/staff/clock');
    setState(() {
      if (res['ok'] == true) {
        _data = res['data'] as Map<String, dynamic>;
        _error = null;
      } else {
        _error = res['error']?.toString();
      }
    });
  }

  Future<void> _event(String type) async {
    final res = await widget.client.post('/api/mobile/staff/clock', {'event_type': type});
    if (res['ok'] == true) {
      setState(() => _data = res['data'] as Map<String, dynamic>);
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${res['error']}')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) return Center(child: Text(_error!));
    if (_data == null) return const Center(child: CircularProgressIndicator());
    final status = _data!['status'] as Map? ?? {};
    final summary = _data!['summary'] as Map? ?? {};
    final state = status['state']?.toString() ?? 'off';
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Status: ${status['label'] ?? state}', style: Theme.of(context).textTheme.titleLarge),
          Text('Heute: ${summary['worked_display'] ?? '0:00'} h'),
          const SizedBox(height: 24),
          if (state == 'off')
            FilledButton(onPressed: () => _event('clock_in'), child: const Text('Einstempeln')),
          if (state == 'working') ...[
            FilledButton(onPressed: () => _event('break_start'), child: const Text('Pause starten')),
            const SizedBox(height: 8),
            FilledButton.tonal(onPressed: () => _event('clock_out'), child: const Text('Ausstempeln')),
          ],
          if (state == 'break')
            FilledButton(onPressed: () => _event('break_end'), child: const Text('Pause beenden')),
          TextButton(onPressed: _load, child: const Text('Aktualisieren')),
        ],
      ),
    );
  }
}

class KontoTab extends StatefulWidget {
  const KontoTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<KontoTab> createState() => _KontoTabState();
}

class _KontoTabState extends State<KontoTab> {
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    widget.client.get('/api/mobile/staff/konto').then((res) {
      if (res['ok'] == true) setState(() => _data = res['data'] as Map<String, dynamic>);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_data == null) return const Center(child: CircularProgressIndicator());
    final lots = (_data!['lots'] as List?) ?? [];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Saldo: ${_data!['balance_display']} h', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 12),
        ...lots.map((l) {
          final m = l as Map;
          return ListTile(
            title: Text('${m['remaining_display']} h'),
            subtitle: Text('Ang. ${m['accrued_date']} · bis ${m['expires_at']}'),
          );
        }),
      ],
    );
  }
}

class AbsencesTab extends StatefulWidget {
  const AbsencesTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<AbsencesTab> createState() => _AbsencesTabState();
}

class _AbsencesTabState extends State<AbsencesTab> {
  List<dynamic> _rows = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await widget.client.get('/api/mobile/staff/absences');
    if (res['ok'] == true) {
      setState(() => _rows = (res['data']?['absences'] as List?) ?? []);
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        itemCount: _rows.length,
        itemBuilder: (_, i) {
          final r = _rows[i] as Map;
          return ListTile(
            title: Text('${r['type_label']} · ${r['status_label']}'),
            subtitle: Text('${r['date_from']} – ${r['date_to']} (${r['days_count']} T.)'),
          );
        },
      ),
    );
  }
}

class ShiftsTab extends StatefulWidget {
  const ShiftsTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<ShiftsTab> createState() => _ShiftsTabState();
}

class _ShiftsTabState extends State<ShiftsTab> {
  List<dynamic> _shifts = [];

  @override
  void initState() {
    super.initState();
    widget.client.get('/api/mobile/staff/shifts').then((res) {
      if (res['ok'] == true) {
        setState(() => _shifts = (res['data']?['shifts'] as List?) ?? []);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_shifts.isEmpty) {
      return const Center(child: Text('Keine Schichten diese Woche.'));
    }
    return ListView.builder(
      itemCount: _shifts.length,
      itemBuilder: (_, i) {
        final s = _shifts[i] as Map;
        return ListTile(
          title: Text('${s['date']} · ${s['template_name']}'),
          subtitle: Text('${s['start_time']} – ${s['end_time']}'),
        );
      },
    );
  }
}

class DocsTab extends StatefulWidget {
  const DocsTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<DocsTab> createState() => _DocsTabState();
}

class _DocsTabState extends State<DocsTab> {
  List<dynamic> _docs = [];

  @override
  void initState() {
    super.initState();
    widget.client.get('/api/mobile/staff/documents').then((res) {
      if (res['ok'] == true) {
        setState(() => _docs = (res['data']?['documents'] as List?) ?? []);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_docs.isEmpty) {
      return const Center(child: Text('Keine Dokumente in der Akte.'));
    }
    return ListView.builder(
      itemCount: _docs.length,
      itemBuilder: (_, i) {
        final d = _docs[i] as Map;
        return ListTile(
          title: Text('${d['label']}'),
          subtitle: Text('${d['name']}'),
        );
      },
    );
  }
}

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key, required this.client});
  final ApiClient client;
  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    widget.client.get('/api/mobile/staff/profile').then((res) {
      if (res['ok'] == true) setState(() => _data = res['data'] as Map<String, dynamic>);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_data == null) return const Center(child: CircularProgressIndicator());
    final c = _data!['contact'] as Map? ?? {};
    final e = _data!['employee'] as Map? ?? {};
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ListTile(title: Text('${c['label']}'), subtitle: Text('${c['email']}')),
        ListTile(title: const Text('Tätigkeit'), subtitle: Text('${e['job_type'] ?? '—'}')),
        ListTile(title: const Text('Arbeitszeit'), subtitle: Text('${e['working_hours'] ?? '—'}')),
        ListTile(title: const Text('Eintritt'), subtitle: Text('${e['entry_date'] ?? e['contract_start'] ?? '—'}')),
      ],
    );
  }
}
