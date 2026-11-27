import 'package:flutter_test/flutter_test.dart';
import 'package:dg_kalender/api_message.dart';
import 'package:dg_kalender/api_base_url.dart';

void main() {
  group('api_message', () {
    test('apiIsOk', () {
      expect(apiIsOk({'ok': true, 'data': {}}), isTrue);
      expect(apiIsOk({'ok': false, 'error': 'x', 'code': 'bad_request'}), isFalse);
      expect(apiIsOk(null), isFalse);
    });

    test('apiCodeMessage unauthorized / no_crm_user', () {
      expect(apiCodeMessage('unauthorized'), contains('Sitzung'));
      expect(apiCodeMessage('no_crm_user'), contains('CRM-Benutzer'));
      expect(apiCodeMessage('unknown_xyz'), isNull);
    });

    test('apiUserMessage bevorzugt error, sonst code', () {
      expect(
        apiUserMessage({'ok': false, 'error': 'Server sagt nein', 'code': 'bad_request'}),
        'Server sagt nein',
      );
      expect(
        apiUserMessage({'ok': false, 'error': '', 'code': 'rate_limited'}),
        contains('warten'),
      );
    });

    test('apiIsSessionExpired / booking_disabled', () {
      expect(apiIsSessionExpired({'code': 'unauthorized'}), isTrue);
      expect(apiIsBookingDisabled({'code': 'booking_disabled'}), isTrue);
      expect(apiIsSlotUnavailable({'code': 'slot_unavailable'}), isTrue);
    });
  });

  group('api_base_url', () {
    test('normalizeBaseUrl https', () {
      expect(normalizeBaseUrl('https://firma.example/'), 'https://firma.example');
      expect(normalizeBaseUrl('firma.example'), 'https://firma.example');
    });

    test('resolveCrmBaseUrl aus app-connect Link', () {
      expect(
        resolveCrmBaseUrl('https://firma.example/app-connect?base=https://firma.example'),
        'https://firma.example',
      );
    });

    test('leere Adresse wirft', () {
      expect(() => normalizeBaseUrl(''), throwsArgumentError);
    });
  });
}
