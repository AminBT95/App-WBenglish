import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import 'api.dart';

class WordsPage extends StatefulWidget {
  final Api api;
  const WordsPage({super.key, required this.api});
  @override
  State<WordsPage> createState() => _WordsPageState();
}

class _WordsPageState extends State<WordsPage> {
  late Future<Map<String, dynamic>> request;
  @override
  void initState() {
    super.initState();
    request = widget.api.get('/words');
  }

  Future<void> refresh() async {
    final next = widget.api.get('/words');
    setState(() => request = next);
    // FutureBuilder displays any error.
    try {
      await next;
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Mot du jour'),
      actions: [
        IconButton(
          onPressed: refresh,
          icon: const Icon(Icons.refresh),
          tooltip: 'Actualiser',
        ),
      ],
    ),
    body: FutureBuilder<Map<String, dynamic>>(
      future: request,
      builder: (context, s) {
        if (s.hasError)
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(s.error.toString()),
                  OutlinedButton(
                    onPressed: refresh,
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            ),
          );
        if (!s.hasData) return const Center(child: CircularProgressIndicator());
        final words = s.data!['words'] as List;
        return RefreshIndicator(
          onRefresh: refresh,
          child: ListView(
            padding: const EdgeInsets.all(20),
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              const Text(
                'Utilisez chaque mot dans vos propres phrases, puis envoyez vos enregistrements au formateur.',
              ),
              const SizedBox(height: 16),
              if (words.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('Aucun mot disponible pour vos cours.'),
                  ),
                ),
              ...words.map(
                (w) => Card(
                  child: ListTile(
                    leading: Icon(
                      w['corrected'] == true
                          ? Icons.mark_chat_read_outlined
                          : Icons.mic_none,
                    ),
                    title: Text(w['word'] as String),
                    subtitle: Text(
                      '${w['date']} · ${w['corrected'] == true
                          ? 'Correction disponible'
                          : w['submitted'] == true
                          ? 'En attente de correction'
                          : 'À vous de parler'}',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              WordPractice(api: widget.api, id: w['id'] as int),
                        ),
                      );
                      if (mounted) refresh();
                    },
                  ),
                ),
              ),
            ],
          ),
        );
      },
    ),
  );
}

class WordPractice extends StatefulWidget {
  final Api api;
  final int id;
  const WordPractice({super.key, required this.api, required this.id});
  @override
  State<WordPractice> createState() => _WordPracticeState();
}

class _WordPracticeState extends State<WordPractice>
    with WidgetsBindingObserver {
  final recorder = AudioRecorder();
  final player = AudioPlayer();
  final clips = <String>[];
  final cached = <String, String>{};
  Directory? directory;
  Map<String, dynamic>? data, pending;
  String? error, playing;
  Timer? timer;
  StreamSubscription<PlayerState>? playback;
  bool busy = false, recording = false, ready = false;
  int seconds = 0;
  String get route => '/words/${widget.id}';
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    playback = player.playerStateStream.listen((_) {
      if (mounted) setState(() {});
    });
    initialize();
  }

  Future<void> initialize() async {
    try {
      final dir = await getTemporaryDirectory();
      await dir.create(recursive: true);
      final own = await dir.createTemp('wb_word_');
      if (!mounted) {
        await own.delete(recursive: true);
        return;
      }
      directory = own;
      ready = true;
      await refresh();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

  Future<void> refresh() async {
    if (busy || recording) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await player.stop();
      cached.clear();
      playing = null;
      final result = await widget.api.get(route);
      if (mounted) setState(() => data = result);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) {
      // Stop capture/playback when leaving the app; never record in background.
      if (recording && !busy) stopRecord();
      player.pause();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    timer?.cancel();
    playback?.cancel();
    final dir = directory;
    Future<void>(() async {
      try {
        await recorder.dispose();
      } catch (_) {}
      try {
        await player.dispose();
      } catch (_) {}
      try {
        if (dir != null && await dir.exists())
          await dir.delete(recursive: true);
      } catch (_) {}
    });
    super.dispose();
  }

  Future<void> startRecord() async {
    if (busy || recording || !ready || clips.length >= 3 || pending != null)
      return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await player.stop();
      if (!await recorder.hasPermission())
        throw ApiFailure(
          'Autorisez le microphone dans les réglages du téléphone.',
        );
      if (!mounted) return;
      final path =
          '${directory!.path}/take_${DateTime.now().microsecondsSinceEpoch}.m4a';
      await recorder.start(
        const RecordConfig(
          encoder: AudioEncoder.aacLc,
          bitRate: 64000,
          sampleRate: 16000,
          numChannels: 1,
        ),
        path: path,
      );
      if (!mounted) {
        await recorder.cancel();
        return;
      }
      setState(() {
        recording = true;
        seconds = 0;
        playing = null;
      });
      timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted) return;
        setState(() => seconds++);
        if (seconds >= 60) stopRecord();
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> stopRecord() async {
    if (!recording || busy) return;
    timer?.cancel();
    setState(() => busy = true);
    try {
      final path = await recorder.stop();
      if (path == null) throw ApiFailure('Aucun audio enregistré. Réessayez.');
      final f = File(path);
      if (await f.length() > 1048576) {
        await f.delete();
        throw ApiFailure(
          'Audio supérieur à 1 Mo. Réessayez avec une phrase plus courte.',
        );
      }
      if (mounted) setState(() => clips.add(path));
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted)
        setState(() {
          recording = false;
          busy = false;
        });
    }
  }

  Future<void> play(String key, {String? local}) async {
    if (busy || recording) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      if (playing == key &&
          player.playing &&
          player.processingState != ProcessingState.completed) {
        await player.pause();
        if (mounted) setState(() => playing = null);
        return;
      }
      await player.stop();
      String? path = local ?? cached[key];
      if (path == null) {
        final result = await widget.api.get('$route/audio/$key');
        final extension = result['extension'];
        if (!['m4a', 'wav', 'mp3', 'webm', 'ogg'].contains(extension))
          throw ApiFailure('Format audio non reconnu.');
        final bytes = base64Decode(result['data'] as String);
        if (bytes.length > 1048576) throw ApiFailure('Audio trop volumineux.');
        path = '${directory!.path}/${key.replaceAll('/', '_')}.$extension';
        await File(path).writeAsBytes(bytes, flush: true);
        cached[key] = path;
      }
      if (!mounted) return;
      await player.setFilePath(path);
      if (!mounted) return;
      setState(() => playing = key);
      // play completes at the end; keep the screen usable while playing.
      unawaited(
        player.play().catchError((Object e) {
          if (mounted) setState(() => error = e.toString());
        }),
      );
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> send() async {
    if (busy || recording || clips.isEmpty) return;
    if (pending == null) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Envoyer au formateur ?'),
          content: Text(
            'Envoyer ${clips.length} audio(s) ? Après envoi, cette réponse ne sera plus modifiable dans le pilote.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Envoyer'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await player.stop();
      if (pending == null) {
        final encoded = <String>[];
        for (final path in clips) {
          encoded.add(base64Encode(await File(path).readAsBytes()));
        }
        final random = Random.secure();
        pending = {
          'request_id': List.generate(
            16,
            (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
          ).join(),
          'clips': encoded,
        };
      }
      final result = await widget.api.post('$route/submit', pending!);
      if (mounted)
        setState(() {
          data = result;
          pending = null;
          playing = null;
        });
      for (final path in clips) {
        try {
          await File(path).delete();
        } catch (_) {}
      }
      clips.clear();
    } catch (e) {
      if (e is ApiFailure && [400, 413].contains(e.statusCode)) pending = null;
      if (mounted)
        setState(
          () => error =
              '$e\nSi la connexion a été interrompue, « Réessayer l’envoi » réutilise la même réponse sans doublon.',
        );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> removeClip(int i) async {
    setState(() => busy = true);
    try {
      await player.stop();
      await File(clips[i]).delete();
      if (mounted)
        setState(() {
          clips.removeAt(i);
          playing = null;
        });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget audioButton(String label, String key, {String? local}) =>
      OutlinedButton.icon(
        onPressed: busy || recording ? null : () => play(key, local: local),
        icon: Icon(
          playing == key &&
                  player.playing &&
                  player.processingState != ProcessingState.completed
              ? Icons.pause
              : Icons.play_arrow,
        ),
        label: Text(label),
      );
  @override
  Widget build(BuildContext context) {
    final d = data;
    final submitted = d?['submitted'] == true;
    final feedback = d?['feedback'] as Map<String, dynamic>?;
    return PopScope(
      canPop: !busy && !recording,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('À vous de parler'),
          actions: [
            IconButton(
              onPressed: busy || recording ? null : refresh,
              icon: const Icon(Icons.refresh),
              tooltip: 'Actualiser',
            ),
          ],
        ),
        body: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            if (busy) const LinearProgressIndicator(),
            if (error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(error!, style: const TextStyle(color: Colors.red)),
              ),
            if (d != null) ...[
              Text(
                d['word'] as String,
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              Text(d['date'] as String),
              const SizedBox(height: 20),
              Text(d['instruction'] as String),
              const SizedBox(height: 24),
              if (!submitted) ...[
                const Text(
                  'Enregistrez 1 à 3 phrases. Maximum 60 secondes par audio. Les brouillons sont supprimés en quittant cette page.',
                ),
                const SizedBox(height: 12),
                if (recording)
                  Text(
                    'Enregistrement : $seconds / 60 s',
                    style: const TextStyle(color: Colors.red),
                  ),
                FilledButton.icon(
                  onPressed:
                      busy ||
                          !ready ||
                          pending != null ||
                          (!recording && clips.length >= 3)
                      ? null
                      : recording
                      ? stopRecord
                      : startRecord,
                  icon: Icon(recording ? Icons.stop : Icons.mic),
                  label: Text(recording ? 'Arrêter' : 'Enregistrer une phrase'),
                ),
                ...List.generate(
                  clips.length,
                  (i) => Row(
                    children: [
                      Expanded(
                        child: audioButton(
                          'Écouter la phrase ${i + 1}',
                          'local/$i',
                          local: clips[i],
                        ),
                      ),
                      IconButton(
                        tooltip: 'Supprimer la phrase',
                        onPressed: busy || recording || pending != null
                            ? null
                            : () => removeClip(i),
                        icon: const Icon(Icons.delete_outline),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: busy || recording || clips.isEmpty ? null : send,
                  child: Text(
                    pending == null
                        ? 'Envoyer au formateur'
                        : 'Réessayer l’envoi',
                  ),
                ),
              ] else ...[
                const Chip(label: Text('Réponse envoyée')),
                ...List.generate(
                  (d['clips'] as num).toInt(),
                  (i) => audioButton('Ma phrase ${i + 1}', 'student/$i'),
                ),
                const Divider(height: 36),
                Text(
                  'Correction du formateur',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                if (d['corrected'] != true)
                  const Text(
                    'Votre réponse attend une correction. Revenez ici et appuyez sur Actualiser.',
                  ),
                if ((feedback?['text'] as String? ?? '').isNotEmpty)
                  Text(feedback!['text'] as String),
                if (feedback?['audio'] == true)
                  audioButton('Écouter la correction', 'teacher/0'),
              ],
            ] else if (!busy)
              OutlinedButton(
                onPressed: ready ? refresh : initialize,
                child: const Text('Réessayer'),
              ),
          ],
        ),
      ),
    );
  }
}
