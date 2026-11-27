import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:dg_kalender/main.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('App baut MaterialApp', (WidgetTester tester) async {
    await tester.pumpWidget(const DgKalenderApp());
    expect(find.byType(MaterialApp), findsOneWidget);
  });

  testWidgets('Bootstrap zeigt CRM-Adresse nach Laden', (WidgetTester tester) async {
    await tester.pumpWidget(const DgKalenderApp());
    await tester.pump(); // loading
    await tester.pumpAndSettle();
    expect(find.textContaining('CRM-Adresse'), findsWidgets);
    expect(find.text('Weiter zur Anmeldung'), findsOneWidget);
  });

  testWidgets('LoginPage Validierung ohne Eingabe', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: LoginPage(baseUrl: 'https://example.test'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Anmelden'), findsWidgets);
    await tester.tap(find.widgetWithText(FilledButton, 'Anmelden'));
    await tester.pump();
    expect(find.text('Bitte E-Mail eingeben.'), findsOneWidget);
  });
}
