import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wbenglish_mobile/api.dart';
import 'package:wbenglish_mobile/main.dart';

class FakeApi extends Api {
  @override
  Future<Map<String, dynamic>> get(String route) async {
    if (route == '/courses')
      return {
        'courses': [
          {
            'id': 123,
            'title': 'English A1',
            'summary': '',
            'url': 'https://example.test',
          },
        ],
        'unavailable': 0,
      };
    return {
      'id': 123,
      'title': 'English A1',
      'url': 'https://example.test',
      'sections': [
        {
          'title': 'Listening',
          'lessons': [
            {
              'id': 456,
              'title': 'Locked audio',
              'type': 'audio',
              'supported': true,
              'locked': true,
              'completed': false,
            },
            {
              'id': 457,
              'title': 'Completed lesson',
              'type': 'text',
              'supported': true,
              'locked': false,
              'completed': true,
            },
          ],
        },
      ],
    };
  }
}

void main() {
  test('Empty credentials rejected before any network call', () {
    expect(() => Api().login('', ''), throwsA(isA<ApiFailure>()));
    expect(
      () => Api().login('user:invalid', 'secret'),
      throwsA(isA<ApiFailure>()),
    );
  });
  testWidgets('Course opens curriculum and locked lesson cannot be opened', (
    tester,
  ) async {
    await tester.pumpWidget(MaterialApp(home: CoursesPage(api: FakeApi())));
    await tester.pumpAndSettle();
    await tester.tap(find.text('English A1'));
    await tester.pumpAndSettle();
    expect(find.text('Listening'), findsOneWidget);
    final tile = tester.widget<ListTile>(
      find.ancestor(
        of: find.text('Locked audio'),
        matching: find.byType(ListTile),
      ),
    );
    expect(tile.onTap, isNull);
    expect(find.text('Déjà terminée'), findsOneWidget);
  });
  testWidgets('Logout returns to login without retaining visible course data', (
    tester,
  ) async {
    await tester.pumpWidget(MaterialApp(home: CoursesPage(api: FakeApi())));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Déconnexion'));
    await tester.pumpAndSettle();
    expect(find.text('English A1'), findsNothing);
    expect(find.text('Identifiant WordPress'), findsOneWidget);
  });
}
