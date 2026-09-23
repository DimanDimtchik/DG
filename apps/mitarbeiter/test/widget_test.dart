import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:dg_mitarbeiter/main.dart';

void main() {
  testWidgets('App baut MaterialApp', (WidgetTester tester) async {
    await tester.pumpWidget(const DgMitarbeiterApp());
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
