import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  runApp(const DgKalenderApp());
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
  ApiClient(this.baseUrl, {this.token});

  String baseUrl;
  String? token;

  Uri _u(String path, [Map<String, String>? q]) =>
      Uri.parse('${baseUrl.replaceAll(RegExp(r'/+$'), '')}$path')
          .replace(queryParameters: q);

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
    _url.text = p.getString('base_url') ?? '';
    _token = p.getString('token');
    setState(() => _loading = false);
    if (_url.text.isNotEmpty && _token != null && _token!.isNotEmpty) {
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => HomePage(client: ApiClient(_url.text, token: _token)),
        ),
      );
    }
  }

  Future<void> _saveUrl() async {
    final p = await SharedPreferences.getInstance();
    await p.setString('base_url', _url.text.trim());
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => LoginPage(baseUrl: _url.text.trim()),
      ),
    );
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
    final client = ApiClient(widget.baseUrl);
    try {
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
      await p.setString('base_url', widget.baseUrl);
      await p.setString('token', token);
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => HomePage(client: ApiClient(widget.baseUrl, token: token)),
        ),
        (_) => false,
      );
    } catch (e) {
      setState(() => _error = e.toString());
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
  String? _error;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    try {
      final b = await widget.client.get('/api/mobile/customer/bookings');
      final c = await widget.client.get('/api/mobile/customer/catalog');
      setState(() {
        _bookings = (b['data']?['bookings'] as List?) ?? [];
        _articles = (c['data']?['articles'] as List?) ?? [];
        _error = b['ok'] == true ? null : (b['error']?.toString());
      });
    } catch (e) {
      setState(() => _error = e.toString());
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Meine Termine'),
        actions: [
          IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: _error != null
          ? Center(child: Text(_error!))
          : _tab == 0
              ? ListView.builder(
                  itemCount: _bookings.length,
                  itemBuilder: (_, i) {
                    final b = _bookings[i] as Map<String, dynamic>;
                    return ListTile(
                      title: Text('${b['slot_datetime']}'),
                      subtitle: Text('${b['status_label'] ?? b['status']} · ${b['booking_code']}'),
                    );
                  },
                )
              : ListView.builder(
                  itemCount: _articles.length,
                  itemBuilder: (_, i) {
                    final a = _articles[i] as Map<String, dynamic>;
                    return ListTile(
                      title: Text('${a['title']}'),
                      subtitle: Text('${a['duration_minutes']} min · ${a['price_label'] ?? ''}'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () async {
                        final date = DateTime.now().add(const Duration(days: 1));
                        final dateStr =
                            '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
                        final slotsRes = await widget.client.get(
                          '/api/mobile/customer/slots',
                          {'article_id': '${a['id']}', 'date': dateStr},
                        );
                        final slots = (slotsRes['data']?['slots'] as List?) ?? [];
                        if (!context.mounted) return;
                        if (slots.isEmpty) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Keine Slots morgen — anderes Datum später.')),
                          );
                          return;
                        }
                        final slot = slots.first.toString();
                        final book = await widget.client.post('/api/mobile/customer/bookings', {
                          'article_id': a['id'],
                          'slot_datetime': slot,
                        });
                        if (!context.mounted) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              book['ok'] == true
                                  ? 'Termin gebucht'
                                  : (book['error'] ?? 'Fehler').toString(),
                            ),
                          ),
                        );
                        await _reload();
                        setState(() => _tab = 0);
                      },
                    );
                  },
                ),
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
