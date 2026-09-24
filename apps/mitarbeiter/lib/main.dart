import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

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
  Timer? _tick;
  Timer? _refresh;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _load();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _now = DateTime.now());
    });
    _refresh = Timer.periodic(const Duration(seconds: 30), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _tick?.cancel();
    _refresh?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    final res = await widget.client.get('/api/mobile/staff/clock');
    if (!mounted) return;
    setState(() {
      if (res['ok'] == true) {
        _data = res['data'] as Map<String, dynamic>;
        _error = null;
      } else if (!silent) {
        _error = res['error']?.toString();
      }
    });
  }

  Future<void> _event(String type) async {
    final res = await widget.client.post('/api/mobile/staff/clock', {'event_type': type});
    if (!mounted) return;
    if (res['ok'] == true) {
      setState(() => _data = res['data'] as Map<String, dynamic>);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${res['error']}')));
    }
  }

  DateTime? _parseServerTime(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final normalized = raw.trim().replaceFirst(' ', 'T');
    return DateTime.tryParse(normalized);
  }

  String _fmtHm(int totalMinutes) {
    final m = totalMinutes.abs();
    final h = m ~/ 60;
    final min = m % 60;
    final body = '$h:${min.toString().padLeft(2, '0')}';
    return totalMinutes < 0 ? '-$body' : body;
  }

  String _fmtDuration(Duration d) {
    final total = d.inSeconds < 0 ? Duration.zero : d;
    final h = total.inHours;
    final m = total.inMinutes.remainder(60);
    final s = total.inSeconds.remainder(60);
    if (h > 0) {
      return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
    }
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  String _fmtClock(DateTime t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}:${t.second.toString().padLeft(2, '0')}';

  Color _stateColor(String state) {
    switch (state) {
      case 'working':
        return const Color(0xFF15803D);
      case 'break':
        return const Color(0xFFC2410C);
      default:
        return const Color(0xFF64748B);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Erneut laden')),
            ],
          ),
        ),
      );
    }
    if (_data == null) return const Center(child: CircularProgressIndicator());

    final status = Map<String, dynamic>.from(_data!['status'] as Map? ?? {});
    final summary = Map<String, dynamic>.from(_data!['summary'] as Map? ?? {});
    final state = status['state']?.toString() ?? 'off';
    final events = (summary['events'] as List?) ?? const [];
    final warnings = (summary['warnings'] as List?) ?? const [];

    final sinceAt = _parseServerTime(status['since']?.toString());
    var sessionStartAt = _parseServerTime(status['session_start']?.toString());
    var sessionStartDisplay = status['session_start_display']?.toString();
    if (sessionStartAt == null || sessionStartDisplay == null) {
      for (final raw in events) {
        final e = Map<String, dynamic>.from(raw as Map);
        if (e['event_type']?.toString() == 'clock_in') {
          sessionStartAt ??= _parseServerTime(e['occurred_at']?.toString());
          sessionStartDisplay ??= e['occurred_display']?.toString();
          break;
        }
      }
    }
    final segmentElapsed = sinceAt == null ? null : _now.difference(sinceAt);
    final sessionElapsed = sessionStartAt == null || state == 'off'
        ? null
        : _now.difference(sessionStartAt);

    final workedMin = (summary['worked_minutes'] as num?)?.toInt() ?? 0;
    final breakMin = (summary['break_minutes'] as num?)?.toInt() ?? 0;
    final scheduledMin = (summary['scheduled_minutes'] as num?)?.toInt() ?? 0;
    final remaining = math.max(0, scheduledMin - workedMin);
    final progress = scheduledMin > 0 ? (workedMin / scheduledMin).clamp(0.0, 1.2) : 0.0;

    final shiftName = (summary['shift_name'] ?? '').toString().trim();
    final shiftStart = (summary['shift_start'] ?? '').toString().trim();
    final shiftEnd = (summary['shift_end'] ?? '').toString().trim();
    final shiftLine = [
      if (shiftName.isNotEmpty) shiftName,
      if (shiftStart.isNotEmpty && shiftEnd.isNotEmpty) '$shiftStart–$shiftEnd Uhr',
    ].join(' · ');

    final theme = Theme.of(context);
    final stateColor = _stateColor(state);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _fmtClock(_now),
                  style: theme.textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Aktualisieren',
                onPressed: _load,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Card(
            color: stateColor.withValues(alpha: 0.08),
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: BorderSide(color: stateColor.withValues(alpha: 0.35)),
            ),
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    (status['label'] ?? state).toString(),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      color: stateColor,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (segmentElapsed != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      state == 'break'
                          ? 'Pause seit ${_fmtDuration(segmentElapsed)}'
                          : 'Aktueller Abschnitt seit ${_fmtDuration(segmentElapsed)}',
                      style: theme.textTheme.titleMedium,
                    ),
                  ],
                  if (sessionStartDisplay != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Arbeitsbeginn: $sessionStartDisplay'
                      '${sessionElapsed != null ? '  ·  ${_fmtDuration(sessionElapsed)} insgesamt' : ''}',
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    ),
                  ] else if (status['since_display'] != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Seit ${status['since_display']}',
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    ),
                  ],
                  if (shiftLine.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.calendar_view_day, size: 18, color: theme.colorScheme.primary),
                        const SizedBox(width: 6),
                        Expanded(child: Text(shiftLine, style: theme.textTheme.bodyMedium)),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.55,
            children: [
              _MetricTile(
                label: 'Gearbeitet',
                value: '${_fmtHm(workedMin)} h',
                icon: Icons.timer_outlined,
              ),
              _MetricTile(
                label: 'Pause',
                value: '${_fmtHm(breakMin)} h',
                icon: Icons.coffee_outlined,
              ),
              _MetricTile(
                label: 'Soll heute',
                value: '${summary['scheduled_display'] ?? _fmtHm(scheduledMin)} h',
                icon: Icons.flag_outlined,
              ),
              _MetricTile(
                label: scheduledMin > 0 && workedMin >= scheduledMin ? 'Über Soll' : 'Noch bis Soll',
                value: scheduledMin < 1
                    ? '—'
                    : '${_fmtHm(workedMin >= scheduledMin ? workedMin - scheduledMin : remaining)} h',
                icon: Icons.hourglass_bottom,
              ),
            ],
          ),
          if (scheduledMin > 0) ...[
            const SizedBox(height: 12),
            Text('Tagesfortschritt', style: theme.textTheme.labelLarge),
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: progress > 1 ? 1 : progress,
                minHeight: 10,
                backgroundColor: theme.colorScheme.surfaceContainerHighest,
                color: progress > 1 ? const Color(0xFFB45309) : theme.colorScheme.primary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${_fmtHm(workedMin)} / ${_fmtHm(scheduledMin)} h'
              '${progress > 1 ? '  (+${_fmtHm(workedMin - scheduledMin)} über Soll)' : ''}',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          ],
          if (warnings.isNotEmpty) ...[
            const SizedBox(height: 14),
            ...warnings.map((w) => Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Material(
                    color: const Color(0xFFFFF7ED),
                    borderRadius: BorderRadius.circular(10),
                    child: ListTile(
                      dense: true,
                      leading: const Icon(Icons.info_outline, color: Color(0xFFC2410C)),
                      title: Text('$w', style: const TextStyle(fontSize: 13)),
                    ),
                  ),
                )),
          ],
          const SizedBox(height: 18),
          if (state == 'off')
            FilledButton.icon(
              onPressed: () => _event('clock_in'),
              icon: const Icon(Icons.login),
              label: const Text('Einstempeln'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            ),
          if (state == 'working') ...[
            FilledButton.icon(
              onPressed: () => _event('break_start'),
              icon: const Icon(Icons.coffee),
              label: const Text('Pause starten'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            ),
            const SizedBox(height: 8),
            FilledButton.tonalIcon(
              onPressed: () => _event('clock_out'),
              icon: const Icon(Icons.logout),
              label: const Text('Ausstempeln'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            ),
          ],
          if (state == 'break')
            FilledButton.icon(
              onPressed: () => _event('break_end'),
              icon: const Icon(Icons.play_arrow),
              label: const Text('Pause beenden'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            ),
          const SizedBox(height: 22),
          Text('Heutige Stempel', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          if (events.isEmpty)
            Text(
              'Noch keine Stempel heute.',
              style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            )
          else
            ...events.map((raw) {
              final e = Map<String, dynamic>.from(raw as Map);
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: Icon(_eventIcon(e['event_type']?.toString())),
                  title: Text('${e['event_label'] ?? e['event_type']}'),
                  subtitle: Text('${e['occurred_display'] ?? e['occurred_at']}'
                      '${e['source_label'] != null ? ' · ${e['source_label']}' : ''}'),
                ),
              );
            }),
        ],
      ),
    );
  }

  IconData _eventIcon(String? type) {
    switch (type) {
      case 'clock_in':
        return Icons.login;
      case 'clock_out':
        return Icons.logout;
      case 'break_start':
        return Icons.coffee;
      case 'break_end':
        return Icons.play_arrow;
      default:
        return Icons.schedule;
    }
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.label, required this.value, required this.icon});
  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      elevation: 0,
      color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.55),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 18, color: theme.colorScheme.primary),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    label,
                    style: theme.textTheme.labelMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const Spacer(),
            Text(
              value,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
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
