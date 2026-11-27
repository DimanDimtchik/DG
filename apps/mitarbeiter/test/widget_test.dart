import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:dg_mitarbeiter/main.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('App baut MaterialApp', (WidgetTester tester) async {
    await tester.pumpWidget(const DgMitarbeiterApp());
    expect(find.byType(MaterialApp), findsOneWidget);
  });

  testWidgets('Bootstrap zeigt Mitarbeiter-App nach Laden', (WidgetTester tester) async {
    await tester.pumpWidget(const DgMitarbeiterApp());
    await tester.pump();
    await tester.pumpAndSettle(const Duration(seconds: 2));
    expect(find.textContaining('Mitarbeiter-App'), findsWidgets);
    expect(find.text('Weiter'), findsOneWidget);
  });

  testWidgets('LoginPage Validierung ohne Kennung', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: LoginPage(baseUrl: 'https://example.test'),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Kennung'), findsWidgets);
    await tester.tap(find.widgetWithText(FilledButton, 'Anmelden'));
    await tester.pump();
    expect(find.text('Bitte Kennung eingeben.'), findsOneWidget);
  });
}
