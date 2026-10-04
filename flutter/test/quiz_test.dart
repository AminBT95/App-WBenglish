import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wbenglish_mobile/api.dart';
import 'package:wbenglish_mobile/quiz.dart';

class QuizApi extends Api {
  final List<Map<String, dynamic>> submissions = [];
  bool failFirst = false;
  @override
  Future<Map<String, dynamic>> get(String route) async => {
    'title': 'Quiz de test',
    'passing_grade': 60,
    'attempts': 0,
    'version_token': 'server-version',
    'can_submit': true,
    'last_result': null,
    'questions': [
      {
        'id': 501,
        'title': 'Hello ?',
        'text': '',
        'type': 'single_choice',
        'options': [
          {'id': '0', 'label': 'Bonjour'},
          {'id': '1', 'label': 'Au revoir'},
        ],
      },
    ],
  };
  @override
  Future<Map<String, dynamic>> post(
    String route,
    Map<String, dynamic> body,
  ) async {
    submissions.add(body);
    if (failFirst && submissions.length == 1)
      throw ApiFailure('Connexion interrompue');
    return {
      'attempt_id': 42,
      'score': 0,
      'passed': false,
      'saved_at': '2026-10-04 18:00:00',
    };
  }
}

void main() {
  testWidgets('Unanswered quiz is not submitted', (tester) async {
    final api = QuizApi();
    await tester.pumpWidget(
      MaterialApp(home: QuizPage(api: api, courseId: 123, quizId: 500)),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Valider mes réponses'));
    await tester.tap(find.text('Valider mes réponses'));
    await tester.pumpAndSettle();
    expect(api.submissions, isEmpty);
    expect(
      find.text('Réponds à toutes les questions avant de valider.'),
      findsOneWidget,
    );
  });
  testWidgets('Quiz retry preserves request and displays server score', (
    tester,
  ) async {
    final api = QuizApi()..failFirst = true;
    await tester.pumpWidget(
      MaterialApp(home: QuizPage(api: api, courseId: 123, quizId: 500)),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Bonjour'));
    await tester.tap(find.text('Bonjour'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Valider mes réponses'));
    await tester.tap(find.text('Valider mes réponses'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Enregistrer'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Réessayer le même envoi'));
    await tester.tap(find.text('Réessayer le même envoi'));
    await tester.pumpAndSettle();
    expect(api.submissions.length, 2);
    expect(api.submissions[0], equals(api.submissions[1]));
    expect(
      api.submissions[0]['request_id'],
      matches(RegExp(r'^[a-f0-9]{32}$')),
    );
    expect(api.submissions[0]['answers'], {
      '501': ['0'],
    });
    expect(api.submissions[0].containsKey('score'), isFalse);
    expect(find.text('Résultat enregistré : 0 %'), findsOneWidget);
    expect(find.text('Tentative MasterStudy n° 42'), findsOneWidget);
  });
}
