import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import 'api_message.dart';
import 'main.dart' show ApiClient, formatDeDateTime;

/// ES6b / MA3e: Unterschrift aufnehmen + speichern (source=app) — Fehlerpfade klar.
class SignaturePage extends StatefulWidget {
  const SignaturePage({super.key, required this.client});

  final ApiClient client;

  @override
  State<SignaturePage> createState() => _SignaturePageState();
}

class _SignaturePageState extends State<SignaturePage> {
  final GlobalKey _padKey = GlobalKey();
  final List<Offset?> _strokes = <Offset?>[];
  Map<String, dynamic>? _active;
  bool _hasCrmUser = true;
  bool _busy = false;
  bool _dirty = false;
  bool _loaded = false;
  String? _error;
  String? _info;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Map<String, dynamic> _asMap(Object? raw) {
    if (raw is Map<String, dynamic>) return raw;
    if (raw is Map) return Map<String, dynamic>.from(raw);
    return <String, dynamic>{};
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _error = null;
      _info = null;
    });
    try {
      final res = await widget.client.get('/api/mobile/staff/signature');
      if (!mounted) return;
      if (!apiIsOk(res)) {
        setState(() {
          _error = apiUserMessage(res, fallback: 'Unterschrift nicht ladbar.');
          _busy = false;
          _loaded = true;
        });
        return;
      }
      final data = _asMap(res['data']);
      setState(() {
        _hasCrmUser = data['has_crm_user'] == true;
        _active = data['active'] is Map ? _asMap(data['active']) : null;
        _busy = false;
        _loaded = true;
        _info = null;
        if (!_hasCrmUser) {
          _error = apiCodeMessage('no_crm_user') ??
              'Kein CRM-Benutzer mit Ihrem Kontakt verknüpft. Bitte Personal/HR melden.';
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = apiUserMessage(e, fallback: 'Unterschrift nicht ladbar.');
        _busy = false;
        _loaded = true;
      });
    }
  }

  void _clearPad() {
    setState(() {
      _strokes.clear();
      _dirty = false;
      if (_error != null && _error!.contains('unterschreiben')) {
        _error = null;
      }
    });
  }

  Future<void> _savePad() async {
    if (_busy) return;
    if (!_hasCrmUser) {
      setState(() {
        _error = apiCodeMessage('no_crm_user') ??
            'Kein CRM-Benutzer mit Ihrem Kontakt verknüpft. Bitte Personal/HR melden.';
      });
      return;
    }
    if (!_dirty) {
      setState(() => _error = 'Bitte zuerst unterschreiben.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _info = null;
    });
    try {
      final png = await _padToPng();
      if (png == null || png.isEmpty) {
        setState(() {
          _error = 'Zeichnung konnte nicht gelesen werden. Bitte erneut versuchen.';
          _busy = false;
        });
        return;
      }
      final res = await widget.client.post('/api/mobile/staff/signature', {
        'png_base64': base64Encode(png),
      });
      if (!mounted) return;
      if (!apiIsOk(res)) {
        setState(() {
          _error = apiUserMessage(res, fallback: 'Speichern fehlgeschlagen.');
          _busy = false;
        });
        return;
      }
      final data = _asMap(res['data']);
      setState(() {
        _active = data['active'] is Map ? _asMap(data['active']) : null;
        _hasCrmUser = data['has_crm_user'] != false;
        _info = data['message']?.toString() ?? 'Unterschrift gespeichert.';
        _busy = false;
      });
      _clearPad();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_info!)),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = apiUserMessage(e, fallback: 'Speichern fehlgeschlagen.');
        _busy = false;
      });
    }
  }

  Future<void> _pickUpload() async {
    if (_busy) return;
    if (!_hasCrmUser) {
      setState(() {
        _error = apiCodeMessage('no_crm_user') ??
            'Kein CRM-Benutzer mit Ihrem Kontakt verknüpft. Bitte Personal/HR melden.';
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _info = null;
    });
    try {
      final picked = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['png', 'webp'],
        withData: false,
      );
      if (picked == null || picked.files.isEmpty) {
        setState(() => _busy = false);
        return;
      }
      final file = picked.files.first;
      final path = file.path;
      if (path == null || path.isEmpty) {
        setState(() {
          _error = 'Datei konnte nicht gelesen werden.';
          _busy = false;
        });
        return;
      }
      final res = await widget.client.postMultipart(
        '/api/mobile/staff/signature',
        fields: const {},
        fileField: 'signature_file',
        filePath: path,
        filename: file.name,
      );
      if (!mounted) return;
      if (!apiIsOk(res)) {
        setState(() {
          _error = apiUserMessage(res, fallback: 'Upload fehlgeschlagen.');
          _busy = false;
        });
        return;
      }
      final data = _asMap(res['data']);
      setState(() {
        _active = data['active'] is Map ? _asMap(data['active']) : null;
        _hasCrmUser = data['has_crm_user'] != false;
        _info = data['message']?.toString() ?? 'Unterschrift gespeichert.';
        _busy = false;
      });
      _clearPad();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_info!)),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = apiUserMessage(e, fallback: 'Upload fehlgeschlagen.');
        _busy = false;
      });
    }
  }

  Future<Uint8List?> _padToPng() async {
    final boundary = _padKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
    if (boundary == null) return null;
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    return bytes?.buffer.asUint8List();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final preview = '${_active?['preview_url'] ?? ''}'.trim();
    final since = '${_active?['valid_from'] ?? ''}'.trim();
    final source = '${_active?['source'] ?? ''}'.trim();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Unterschrift'),
        actions: [
          IconButton(
            tooltip: 'Aktualisieren',
            onPressed: _busy ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: !_loaded && _busy
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
              children: [
                Text(
                  'Zweck: Unterschriftsbild für Briefe und E-Mails im CRM. '
                  'Einfache elektronische Unterschrift — keine Schriftform und keine qualifizierte Signatur (QES). '
                  'Vorherige Versionen bleiben in der Historie und werden hier nicht gelöscht.',
                  style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 12),
                if (!_hasCrmUser)
                  Card(
                    color: theme.colorScheme.errorContainer,
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Text(
                        apiCodeMessage('no_crm_user') ??
                            'Kein CRM-Benutzer mit Ihrem Kontakt verknüpft. Bitte Personal/HR melden.',
                        style: TextStyle(color: theme.colorScheme.onErrorContainer),
                      ),
                    ),
                  ),
                if (_active != null) ...[
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Aktuelle Unterschrift', style: theme.textTheme.titleMedium),
                          const SizedBox(height: 6),
                          Text('Quelle: ${source.isEmpty ? '—' : source}'),
                          if (since.isNotEmpty) Text('Seit: ${formatDeDateTime(since)}'),
                          if (preview.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: ColoredBox(
                                color: Colors.white,
                                child: Image.network(
                                  preview,
                                  height: 72,
                                  fit: BoxFit.contain,
                                  errorBuilder: (_, __, ___) => Text(
                                    'Vorschau nicht ladbar.',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ] else if (_hasCrmUser && _loaded && !_busy)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Text(
                        'Noch keine aktive Unterschrift hinterlegt.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ),
                const SizedBox(height: 8),
                Text('Neu zeichnen', style: theme.textTheme.titleMedium),
                const SizedBox(height: 8),
                DecoratedBox(
                  decoration: BoxDecoration(
                    border: Border.all(color: theme.colorScheme.outlineVariant),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: RepaintBoundary(
                      key: _padKey,
                      child: SizedBox(
                        height: 180,
                        width: double.infinity,
                        child: ColoredBox(
                          color: Colors.white,
                          child: GestureDetector(
                            onPanStart: _hasCrmUser && !_busy
                                ? (d) => setState(() {
                                      _strokes.add(d.localPosition);
                                      _dirty = true;
                                      _error = null;
                                    })
                                : null,
                            onPanUpdate: _hasCrmUser && !_busy
                                ? (d) => setState(() {
                                      _strokes.add(d.localPosition);
                                      _dirty = true;
                                    })
                                : null,
                            onPanEnd: _hasCrmUser && !_busy
                                ? (_) => setState(() => _strokes.add(null))
                                : null,
                            child: CustomPaint(
                              painter: _SignaturePadPainter(_strokes),
                              child: const SizedBox.expand(),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    OutlinedButton(
                      onPressed: _busy || !_hasCrmUser ? null : _clearPad,
                      child: const Text('Leeren'),
                    ),
                    FilledButton(
                      onPressed: _busy || !_hasCrmUser ? null : _savePad,
                      child: Text(_busy ? 'Speichern …' : 'Zeichnung speichern'),
                    ),
                    OutlinedButton.icon(
                      onPressed: _busy || !_hasCrmUser ? null : _pickUpload,
                      icon: const Icon(Icons.upload_file),
                      label: const Text('PNG/WebP laden'),
                    ),
                  ],
                ),
                if (_busy) ...[
                  const SizedBox(height: 16),
                  const Center(child: CircularProgressIndicator()),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(color: theme.colorScheme.error, fontWeight: FontWeight.w600),
                  ),
                  if (!_hasCrmUser || _error!.contains('nicht ladbar')) ...[
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: _busy ? null : _load,
                      child: const Text('Erneut laden'),
                    ),
                  ],
                ],
                if (_info != null) ...[
                  const SizedBox(height: 12),
                  Text(_info!, style: TextStyle(color: theme.colorScheme.primary)),
                ],
              ],
            ),
    );
  }
}

class _SignaturePadPainter extends CustomPainter {
  _SignaturePadPainter(this.strokes);

  final List<Offset?> strokes;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF111111)
      ..strokeWidth = 2.4
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round
      ..style = PaintingStyle.stroke;

    Path? path;
    for (final p in strokes) {
      if (p == null) {
        if (path != null) {
          canvas.drawPath(path, paint);
          path = null;
        }
        continue;
      }
      if (path == null) {
        path = Path()..moveTo(p.dx, p.dy);
      } else {
        path.lineTo(p.dx, p.dy);
      }
    }
    if (path != null) {
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _SignaturePadPainter oldDelegate) => true;
}
