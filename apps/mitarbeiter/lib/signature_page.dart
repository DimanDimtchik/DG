import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import 'main.dart' show ApiClient;

/// ES6b: Minimal-UI Unterschrift aufnehmen + speichern (source=app).
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
  String? _error;
  String? _info;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final res = await widget.client.get('/api/mobile/staff/signature');
      if (!mounted) return;
      if (res['ok'] != true) {
        setState(() {
          _error = res['error']?.toString() ?? 'Unterschrift nicht ladbar';
          _busy = false;
        });
        return;
      }
      final data = Map<String, dynamic>.from(res['data'] as Map? ?? {});
      setState(() {
        _hasCrmUser = data['has_crm_user'] == true;
        _active = data['active'] is Map
            ? Map<String, dynamic>.from(data['active'] as Map)
            : null;
        _busy = false;
        _info = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _busy = false;
      });
    }
  }

  void _clearPad() {
    setState(() {
      _strokes.clear();
      _dirty = false;
    });
  }

  Future<void> _savePad() async {
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
        throw Exception('Zeichnung konnte nicht gelesen werden.');
      }
      final res = await widget.client.post('/api/mobile/staff/signature', {
        'png_base64': base64Encode(png),
      });
      if (!mounted) return;
      if (res['ok'] != true) {
        setState(() {
          _error = res['error']?.toString() ?? 'Speichern fehlgeschlagen';
          _busy = false;
        });
        return;
      }
      final data = Map<String, dynamic>.from(res['data'] as Map? ?? {});
      setState(() {
        _active = data['active'] is Map
            ? Map<String, dynamic>.from(data['active'] as Map)
            : null;
        _hasCrmUser = data['has_crm_user'] != false;
        _info = data['message']?.toString() ?? 'Unterschrift gespeichert.';
        _busy = false;
      });
      _clearPad();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _busy = false;
      });
    }
  }

  Future<void> _pickUpload() async {
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
        throw Exception('Dateipfad fehlt.');
      }
      final res = await widget.client.postMultipart(
        '/api/mobile/staff/signature',
        fields: const {},
        fileField: 'signature_file',
        filePath: path,
        filename: file.name,
      );
      if (!mounted) return;
      if (res['ok'] != true) {
        setState(() {
          _error = res['error']?.toString() ?? 'Upload fehlgeschlagen';
          _busy = false;
        });
        return;
      }
      final data = Map<String, dynamic>.from(res['data'] as Map? ?? {});
      setState(() {
        _active = data['active'] is Map
            ? Map<String, dynamic>.from(data['active'] as Map)
            : null;
        _hasCrmUser = data['has_crm_user'] != false;
        _info = data['message']?.toString() ?? 'Unterschrift gespeichert.';
        _busy = false;
      });
      _clearPad();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
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
    return Scaffold(
      appBar: AppBar(title: const Text('Unterschrift')),
      body: ListView(
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
              child: const Padding(
                padding: EdgeInsets.all(14),
                child: Text(
                  'Kein CRM-Benutzer mit Ihrem Kontakt verknüpft. Bitte Personal/HR melden.',
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
                    Text('Quelle: ${_active!['source'] ?? '—'}'),
                    if ('${_active!['valid_from'] ?? ''}'.trim().isNotEmpty)
                      Text('Seit: ${_active!['valid_from']}'),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
          ] else if (_hasCrmUser && !_busy)
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
                              })
                          : null,
                      onPanUpdate: _hasCrmUser && !_busy
                          ? (d) => setState(() {
                                _strokes.add(d.localPosition);
                                _dirty = true;
                              })
                          : null,
                      onPanEnd: _hasCrmUser && !_busy ? (_) => setState(() => _strokes.add(null)) : null,
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
                child: const Text('Zeichnung speichern'),
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
            Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
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
