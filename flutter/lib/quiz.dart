import 'dart:math';

import 'package:flutter/material.dart';

import 'api.dart';

class QuizPage extends StatefulWidget {
  final Api api;
  final int courseId, quizId;
  const QuizPage({
    super.key,
    required this.api,
    required this.courseId,
    required this.quizId,
  });
  @override
  State<QuizPage> createState() => _QuizPageState();
}

class _QuizPageState extends State<QuizPage> {
  Map<String, dynamic>? quiz, result, frozenPayload;
  final Map<String, Set<String>> answers = {};
  bool loading = true, sending = false;
  String? error;
  String get route => '/courses/${widget.courseId}/quizzes/${widget.quizId}';

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.api.get(route);
      if (!mounted) return;
      setState(() {
        quiz = data;
        result = data['last_result'] == null
            ? null
            : Map<String, dynamic>.from(data['last_result'] as Map);
        answers.clear();
        frozenPayload = null;
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> send() async {
    if (sending || quiz == null) return;
    if (frozenPayload == null) {
      final questions = quiz!['questions'] as List;
      if (questions.any(
        (q) => answers[q['id'].toString()]?.isNotEmpty != true,
      )) {
        setState(
          () => error = 'Réponds à toutes les questions avant de valider.',
        );
        return;
      }
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Enregistrer cette tentative ?'),
          content: const Text(
            'Tes réponses seront corrigées et enregistrées dans MasterStudy. Cette validation compte comme une tentative de quiz.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Continuer le quiz'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Enregistrer'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
      final random = Random.secure();
      frozenPayload = {
        'request_id': List.generate(
          16,
          (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
        ).join(),
        'version_token': quiz!['version_token'],
        'answers': answers.map(
          (key, value) => MapEntry(key, value.toList()..sort()),
        ),
      };
    }
    setState(() {
      sending = true;
      error = null;
      result = null;
    });
    try {
      final saved = await widget.api.post('$route/submit', frozenPayload!);
      if (!mounted) return;
      setState(() {
        result = saved;
      });
    } catch (e) {
      if (mounted)
        setState(
          () => error =
              '${e.toString()}\nTu peux réessayer le même envoi ou relire le résultat depuis WordPress.',
        );
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  Widget resultCard(Map<String, dynamic> saved) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            saved['passed'] == true ? Icons.check_circle : Icons.info_outline,
            size: 40,
          ),
          const SizedBox(height: 12),
          Text(
            'Résultat enregistré : ${saved['score']} %',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          Text(
            saved['passed'] == true
                ? 'Quiz réussi'
                : 'Seuil de réussite non atteint',
          ),
          const SizedBox(height: 8),
          Text('Tentative MasterStudy n° ${saved['attempt_id']}'),
          Text(saved['saved_at'] as String? ?? ''),
          if (saved['notice'] != null) Text(saved['notice'].toString()),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final data = quiz;
    final savedThisVisit =
        frozenPayload != null && result != null && error == null;
    final canAnswer =
        data?['can_submit'] == true && frozenPayload == null && !sending;
    return Scaffold(
      appBar: AppBar(title: const Text('Quiz')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (data != null) ...[
                  Text(
                    data['title'] as String,
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Réussite à ${data['passing_grade']} % • ${data['attempts']} tentative(s) déjà enregistrée(s)',
                  ),
                  const SizedBox(height: 16),
                  if (result != null) resultCard(result!),
                  if (data['can_submit'] != true)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      child: Text(
                        data['blocked_reason'] as String? ??
                            'Quiz indisponible.',
                      ),
                    ),
                  if (!savedThisVisit && data['can_submit'] == true) ...[
                    for (final question in data['questions'] as List)
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                question['title'] as String,
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              if ((question['text'] as String).isNotEmpty)
                                Padding(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 8,
                                  ),
                                  child: Text(question['text'] as String),
                                ),
                              Text(
                                question['type'] == 'multi_choice'
                                    ? 'Plusieurs réponses possibles'
                                    : 'Une seule réponse',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              for (final option in question['options'] as List)
                                CheckboxListTile(
                                  contentPadding: EdgeInsets.zero,
                                  controlAffinity:
                                      ListTileControlAffinity.leading,
                                  title: Text(option['label'] as String),
                                  value:
                                      answers[question['id'].toString()]
                                          ?.contains(option['id']) ??
                                      false,
                                  onChanged: !canAnswer
                                      ? null
                                      : (selected) => setState(() {
                                          final key = question['id'].toString(),
                                              value = option['id'] as String;
                                          if (question['type'] ==
                                              'multi_choice') {
                                            final values = answers.putIfAbsent(
                                              key,
                                              () => <String>{},
                                            );
                                            if (selected == true) {
                                              values.add(value);
                                            } else {
                                              values.remove(value);
                                            }
                                          } else {
                                            answers[key] = selected == true
                                                ? {value}
                                                : <String>{};
                                          }
                                          error = null;
                                        }),
                                ),
                            ],
                          ),
                        ),
                      ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: sending ? null : send,
                      child: Text(
                        sending
                            ? 'Enregistrement…'
                            : frozenPayload == null
                            ? 'Valider mes réponses'
                            : 'Réessayer le même envoi',
                      ),
                    ),
                  ],
                ],
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text(
                      error!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                const SizedBox(height: 16),
                OutlinedButton.icon(
                  onPressed: sending ? null : load,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Relire depuis WordPress'),
                ),
                const Text(
                  'Le résultat affiché est renvoyé par WordPress. Une réussite peut faire progresser le cours.',
                  style: TextStyle(fontSize: 12),
                ),
              ],
            ),
    );
  }
}
