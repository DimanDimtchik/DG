import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:path/path.dart' as p;
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

  Future<({List<int> bytes, String? filename, String? mime})> getBinary(
    String path, [
    Map<String, String>? q,
  ]) async {
    final res = await http.get(_u(path, q), headers: {
      if (token != null) 'Authorization': 'Bearer $token',
    });
    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw Exception('Download fehlgeschlagen (${res.statusCode}).');
    }
    String? filename;
    final disp = res.headers['content-disposition'];
    if (disp != null) {
      final m = RegExp(r'filename="?([^";]+)"?', caseSensitive: false).firstMatch(disp);
      filename = m?.group(1);
    }
    return (bytes: res.bodyBytes, filename: filename, mime: res.headers['content-type']);
  }

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required Map<String, String> fields,
    required String fileField,
    required String filePath,
    String? filename,
  }) async {
    final req = http.MultipartRequest('POST', _u(path));
    if (token != null) {
      req.headers['Authorization'] = 'Bearer $token';
    }
    req.fields.addAll(fields);
    final name = filename ?? p.basename(filePath);
    final ext = p.extension(name).toLowerCase().replaceFirst('.', '');
    MediaType? mime;
    switch (ext) {
      case 'pdf':
        mime = MediaType('application', 'pdf');
        break;
      case 'png':
        mime = MediaType('image', 'png');
        break;
      case 'jpg':
      case 'jpeg':
        mime = MediaType('image', 'jpeg');
        break;
      case 'webp':
        mime = MediaType('image', 'webp');
        break;
    }
    req.files.add(await http.MultipartFile.fromPath(
      fileField,
      filePath,
      filename: name,
      contentType: mime,
    ));
    final streamed = await req.send();
    final body = await streamed.stream.toBytes();
    return jsonDecode(utf8.decode(body)) as Map<String, dynamic>;
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
            Center(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(24),
                child: Image.asset(
                  'assets/branding/app_icon.png',
                  width: 112,
                  height: 112,
                  fit: BoxFit.cover,
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Mitarbeiter-App',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 4),
            Text(
              'Stempel · Schichten · Abwesenheiten · Akte',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: 24),
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
  List<dynamic> _slots = [];
  bool _loading = true;
  String? _error;
  String? _busyType;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await widget.client.get('/api/mobile/staff/documents');
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res['ok'] == true) {
        final data = res['data'] as Map? ?? {};
        _slots = (data['slots'] as List?) ?? const [];
        if (_slots.isEmpty) {
          // Fallback ältere API: nur documents-Liste
          final docs = (data['documents'] as List?) ?? const [];
          _slots = docs
              .map((d) {
                final m = Map<String, dynamic>.from(d as Map);
                return {
                  'type': m['type'],
                  'label': m['label'],
                  'multi': false,
                  'files': [m],
                };
              })
              .toList();
        }
      } else {
        _error = res['error']?.toString() ?? 'Akte nicht ladbar';
      }
    });
  }

  Future<void> _upload(String type, String label) async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
      withData: false,
    );
    if (picked == null || picked.files.isEmpty) return;
    final file = picked.files.first;
    final path = file.path;
    if (path == null || path.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Datei konnte nicht gelesen werden.')),
      );
      return;
    }
    setState(() => _busyType = type);
    final res = await widget.client.postMultipart(
      '/api/mobile/staff/documents',
      fields: {'type': type},
      fileField: 'file',
      filePath: path,
      filename: file.name,
    );
    if (!mounted) return;
    setState(() => _busyType = null);
    if (res['ok'] == true) {
      final data = res['data'] as Map? ?? {};
      setState(() => _slots = (data['slots'] as List?) ?? _slots);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$label hochgeladen.')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${res['error'] ?? 'Upload fehlgeschlagen'}')),
      );
    }
  }

  Future<void> _download(Map doc) async {
    final type = doc['type']?.toString() ?? '';
    if (type.isEmpty) return;
    final q = <String, String>{'type': type};
    if (doc['fileIndex'] != null) {
      q['file'] = '${doc['fileIndex']}';
    }
    try {
      final bin = await widget.client.getBinary('/api/mobile/staff/documents/download', q);
      final name = (bin.filename?.trim().isNotEmpty == true)
          ? bin.filename!
          : (doc['name']?.toString() ?? 'dokument.bin');
      final out = File(p.join(Directory.systemTemp.path, name));
      await out.writeAsBytes(bin.bytes, flush: true);
      if (Platform.isWindows) {
        await Process.start('cmd', ['/c', 'start', '', out.path], runInShell: false);
      } else if (Platform.isMacOS) {
        await Process.start('open', [out.path]);
      } else {
        await Process.start('xdg-open', [out.path]);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Geöffnet: $name')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
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

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 28),
        itemCount: _slots.length + 1,
        itemBuilder: (context, i) {
          if (i == 0) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(4, 0, 4, 12),
              child: Text(
                'Personalakte — alle Dokumenttypen. Fehlende Dateien können Sie hier anhängen (PDF/JPG/PNG).',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
              ),
            );
          }
          final slot = Map<String, dynamic>.from(_slots[i - 1] as Map);
          final type = slot['type']?.toString() ?? '';
          final label = slot['label']?.toString() ?? type;
          final multi = slot['multi'] == true;
          final files = (slot['files'] as List?) ?? const [];
          final busy = _busyType == type;

          return Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(label, style: Theme.of(context).textTheme.titleMedium),
                      ),
                      if (busy)
                        const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      else
                        TextButton.icon(
                          onPressed: () => _upload(type, label),
                          icon: Icon(multi ? Icons.note_add_outlined : Icons.upload_file),
                          label: Text(files.isEmpty ? 'Anhängen' : (multi ? 'Weitere' : 'Ersetzen')),
                        ),
                    ],
                  ),
                  if (files.isEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4, top: 2),
                      child: Text(
                        'Noch kein Dokument hinterlegt.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Theme.of(context).colorScheme.onSurfaceVariant,
                            ),
                      ),
                    )
                  else
                    ...files.map((raw) {
                      final doc = Map<String, dynamic>.from(raw as Map);
                      final uploaded = (doc['uploaded_at'] ?? '').toString().trim();
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          (doc['mime']?.toString().startsWith('image/') ?? false)
                              ? Icons.image_outlined
                              : Icons.picture_as_pdf_outlined,
                        ),
                        title: Text('${doc['name'] ?? 'Datei'}'),
                        subtitle: uploaded.isEmpty ? null : Text('Hochgeladen: $uploaded'),
                        trailing: IconButton(
                          tooltip: 'Öffnen',
                          onPressed: () => _download(doc),
                          icon: const Icon(Icons.open_in_new),
                        ),
                      );
                    }),
                ],
              ),
            ),
          );
        },
      ),
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
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await widget.client.get('/api/mobile/staff/profile');
    if (!mounted) return;
    setState(() {
      if (res['ok'] == true) {
        _data = res['data'] as Map<String, dynamic>;
        _error = null;
      } else {
        _error = res['error']?.toString() ?? 'Profil nicht ladbar';
      }
    });
  }

  Widget _kv(String label, String? value) {
    final v = (value ?? '').trim();
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF64748B))),
      subtitle: Text(
        v.isEmpty ? '—' : v,
        style: TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w600,
          color: v.isEmpty ? const Color(0xFF94A3B8) : null,
        ),
      ),
    );
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

    final c = Map<String, dynamic>.from(_data!['contact'] as Map? ?? {});
    final sections = (_data!['sections'] as List?) ?? const [];
    final banks = (_data!['bank_accounts'] as List?) ?? const [];
    final theme = Theme.of(context);
    final addressLines = (c['address_lines'] as List?)?.map((e) => '$e').where((e) => e.trim().isNotEmpty).toList() ??
        [
          if ('${c['address_street'] ?? ''}'.trim().isNotEmpty) '${c['address_street']}',
          [
            '${c['address_postal'] ?? ''}'.trim(),
            '${c['address_city'] ?? ''}'.trim(),
          ].where((e) => e.isNotEmpty).join(' '),
          if ('${c['address_country'] ?? ''}'.trim().isNotEmpty) '${c['address_country']}',
        ].where((e) => e.trim().isNotEmpty).toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          Text('${c['label'] ?? 'Mitarbeiter'}', style: theme.textTheme.headlineSmall),
          if ('${c['login'] ?? ''}'.trim().isNotEmpty)
            Text('Login: ${c['login']}', style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Kontaktdaten', style: theme.textTheme.titleMedium),
                  _kv('Anrede', c['salutation']?.toString()),
                  _kv('Vorname', c['first_name']?.toString()),
                  _kv('Nachname', c['last_name']?.toString()),
                  _kv('Anzeigename', c['display_name']?.toString()),
                  _kv('E-Mail', c['email']?.toString()),
                  _kv('E-Mail 2', c['email2']?.toString()),
                  _kv('Telefon', c['phone']?.toString()),
                  _kv('Telefon 2', c['phone2']?.toString()),
                  _kv('Kunden-/Personalnr.', c['customer_number']?.toString()),
                  _kv('Adresse', addressLines.isEmpty ? null : addressLines.join('\n')),
                ],
              ),
            ),
          ),
          if (sections.isEmpty) ...[
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Mitarbeiterdaten', style: theme.textTheme.titleMedium),
                    ...(() {
                      final e = Map<String, dynamic>.from(_data!['employee'] as Map? ?? {});
                      return [
                        _kv('Tätigkeit', e['job_type']?.toString()),
                        _kv('Arbeitszeit', e['working_hours']?.toString()),
                        _kv('Eintritt', e['entry_date']?.toString()),
                        _kv('Vertragsbeginn', e['contract_start']?.toString()),
                        _kv('Beschäftigungsverhältnis', e['employment_relationship']?.toString()),
                        _kv('Arbeitsort', e['work_location']?.toString()),
                      ];
                    })(),
                  ],
                ),
              ),
            ),
          ],
          ...sections.map((raw) {
            final sec = Map<String, dynamic>.from(raw as Map);
            final fields = (sec['fields'] as List?) ?? const [];
            return Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${sec['label']}', style: theme.textTheme.titleMedium),
                      ...fields.map((fRaw) {
                        final f = Map<String, dynamic>.from(fRaw as Map);
                        return _kv('${f['label']}', f['value']?.toString());
                      }),
                    ],
                  ),
                ),
              ),
            );
          }),
          if (banks.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...banks.map((raw) {
              final bank = Map<String, dynamic>.from(raw as Map);
              final fields = (bank['fields'] as List?) ?? const [];
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${bank['type_label'] ?? 'Bankkonto'}', style: theme.textTheme.titleMedium),
                        ...fields.map((fRaw) {
                          final f = Map<String, dynamic>.from(fRaw as Map);
                          return _kv('${f['label']}', f['value']?.toString());
                        }),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ],
          const SizedBox(height: 8),
          Text(
            'Änderungen bitte über Personal/HR im CRM melden. Hier sehen Sie Ihre hinterlegten Stammdaten.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}
