import 'dart:async';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:video_player/video_player.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'quiz.dart';

void main() => runApp(const WBEnglish());

class WBEnglish extends StatelessWidget {
  const WBEnglish({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'WB English',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff174d48)),
      scaffoldBackgroundColor: const Color(0xfff6f7f3),
      appBarTheme: const AppBarTheme(backgroundColor: Color(0xfff6f7f3)),
    ),
    home: const LoginPage(),
  );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final user = TextEditingController(), password = TextEditingController();
  final api = Api();
  bool busy = false;
  String? error;
  @override
  void dispose() {
    user.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> connect() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      api.login(user.text, password.text);
      await api.get('/courses');
      if (!mounted) return;
      password.clear();
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => CoursesPage(api: api)),
      );
    } catch (e) {
      api.logout();
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.school_outlined,
                  size: 52,
                  color: Color(0xff174d48),
                ),
                const SizedBox(height: 26),
                Text(
                  'WB English',
                  style: Theme.of(context).textTheme.displaySmall,
                ),
                const SizedBox(height: 8),
                const Text('Votre anglais, à votre rythme.'),
                const SizedBox(height: 28),
                const Chip(label: Text('Prototype • connexion élève de test')),
                const SizedBox(height: 12),
                Text(
                  Api.origin.host,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: user,
                  enabled: !busy,
                  autocorrect: false,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Identifiant WordPress',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: password,
                  enabled: !busy,
                  obscureText: true,
                  enableSuggestions: false,
                  autocorrect: false,
                  onSubmitted: (_) {
                    if (!busy) connect();
                  },
                  decoration: const InputDecoration(
                    labelText: 'Mot de passe d’application',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Pour ce pilote, utilisez le mot de passe d’application créé dans le profil WordPress du compte élève.',
                  style: TextStyle(fontSize: 12),
                ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      error!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: busy ? null : connect,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text(busy ? 'Connexion…' : 'Accéder à mes cours'),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class DataView extends StatefulWidget {
  final Future<Map<String, dynamic>> Function() load;
  final Widget Function(Map<String, dynamic>) render;
  const DataView({super.key, required this.load, required this.render});
  @override
  State<DataView> createState() => _DataViewState();
}

class _DataViewState extends State<DataView> {
  late Future<Map<String, dynamic>> request;
  @override
  void initState() {
    super.initState();
    request = widget.load();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
    future: request,
    builder: (context, snapshot) {
      if (snapshot.hasError)
        return Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(snapshot.error.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: () => setState(() => request = widget.load()),
                  child: const Text('Réessayer'),
                ),
              ],
            ),
          ),
        );
      if (!snapshot.hasData)
        return const Center(child: CircularProgressIndicator());
      return RefreshIndicator(
        onRefresh: () async {
          final next = widget.load();
          setState(() => request = next);
          await next;
        },
        child: widget.render(snapshot.data!),
      );
    },
  );
}

class CoursesPage extends StatelessWidget {
  final Api api;
  const CoursesPage({super.key, required this.api});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Mes cours'),
      actions: [
        IconButton(
          tooltip: 'Déconnexion',
          icon: const Icon(Icons.logout),
          onPressed: () {
            api.logout();
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const LoginPage()),
              (_) => false,
            );
          },
        ),
      ],
    ),
    body: DataView(
      load: () => api.get('/courses'),
      render: (data) {
        final courses = data['courses'] as List;
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'Prêt à progresser ?',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            const Text('Retrouvez les cours autorisés pour ce premier test.'),
            const SizedBox(height: 24),
            if (courses.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Text(
                    'Aucun cours accessible. Vérifiez les IDs autorisés, l’inscription de cet élève et les restrictions du pilote dans WordPress.',
                  ),
                ),
              ),
            if ((data['unavailable'] as num? ?? 0) > 0)
              const Padding(
                padding: EdgeInsets.only(bottom: 16),
                child: Text(
                  'Certains cours sélectionnés ne sont pas accessibles. Vérifiez leur inscription et leurs règles d’accès.',
                ),
              ),
            ...courses.map(
              (course) => Card(
                margin: const EdgeInsets.only(bottom: 14),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) =>
                          CoursePage(api: api, id: course['id'] as int),
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.menu_book_outlined, size: 32),
                        const SizedBox(height: 18),
                        Text(
                          course['title'] as String,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        if ((course['summary'] as String).isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(course['summary'] as String),
                          ),
                        const SizedBox(height: 18),
                        const Row(
                          children: [
                            Text('Voir le programme'),
                            Spacer(),
                            Icon(Icons.arrow_forward),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    ),
  );
}

IconData typeIcon(String type) => switch (type) {
  'audio' => Icons.headphones,
  'quiz' => Icons.quiz_outlined,
  'video' => Icons.play_circle_outline,
  'text' => Icons.article_outlined,
  _ => Icons.extension_outlined,
};

class CoursePage extends StatelessWidget {
  final Api api;
  final int id;
  const CoursePage({super.key, required this.api, required this.id});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Programme du cours')),
    body: DataView(
      load: () => api.get('/courses/$id'),
      render: (course) => ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            course['title'] as String,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 12),
          const Text(
            'La progression provient de WordPress. Valider un quiz enregistre une tentative dans MasterStudy.',
          ),
          const SizedBox(height: 20),
          for (final section in course['sections'] as List) ...[
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                section['title'] as String,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            for (final lesson in section['lessons'] as List)
              Card(
                child: ListTile(
                  leading: Icon(typeIcon(lesson['type'] as String)),
                  title: Text(lesson['title'] as String),
                  subtitle: Text(
                    lesson['locked'] == true
                        ? 'Verrouillée par MasterStudy'
                        : lesson['supported'] != true
                        ? 'À suivre sur le site'
                        : lesson['completed'] == true
                        ? 'Déjà terminée'
                        : 'Ouvrir la leçon',
                  ),
                  trailing: Icon(
                    lesson['locked'] == true
                        ? Icons.lock_outline
                        : lesson['completed'] == true
                        ? Icons.check_circle_outline
                        : Icons.chevron_right,
                  ),
                  onTap: lesson['locked'] == true
                      ? null
                      : () {
                          if (lesson['type'] == 'quiz') {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => QuizPage(
                                  api: api,
                                  courseId: id,
                                  quizId: lesson['id'] as int,
                                ),
                              ),
                            );
                          } else if (lesson['supported'] == true) {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => LessonPage(
                                  api: api,
                                  courseId: id,
                                  lessonId: lesson['id'] as int,
                                ),
                              ),
                            );
                          } else {
                            openWebsite(context, course['url'] as String);
                          }
                        },
                ),
              ),
          ],
        ],
      ),
    ),
  );
}

Future<void> openWebsite(BuildContext context, String url) async {
  final uri = Uri.tryParse(url);
  try {
    if (uri == null ||
        uri.scheme != 'https' ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication))
      throw Exception();
  } catch (_) {
    if (context.mounted)
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Impossible d’ouvrir ce lien HTTPS.')),
      );
  }
}

class LessonPage extends StatelessWidget {
  final Api api;
  final int courseId, lessonId;
  const LessonPage({
    super.key,
    required this.api,
    required this.courseId,
    required this.lessonId,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Ma leçon')),
    body: DataView(
      load: () => api.get('/courses/$courseId/lessons/$lessonId'),
      render: (lesson) => ListView(
        padding: const EdgeInsets.all(22),
        children: [
          Text(
            lesson['title'] as String,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 24),
          if (lesson['type'] == 'audio' &&
              (lesson['media_url'] as String).isNotEmpty)
            AudioLesson(url: lesson['media_url'] as String),
          if (lesson['type'] == 'video' &&
              (lesson['media_url'] as String).isNotEmpty)
            VideoLesson(url: lesson['media_url'] as String),
          if (lesson['website_only'] == true)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(18),
                child: Text(
                  'Ce format de leçon nécessite le lecteur du site dans ce premier pilote.',
                ),
              ),
            ),
          const SizedBox(height: 20),
          SelectableText(
            lesson['text'] as String,
            style: const TextStyle(fontSize: 17, height: 1.65),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: () => openWebsite(context, lesson['url'] as String),
            icon: const Icon(Icons.open_in_new),
            label: const Text('Ouvrir sur le site'),
          ),
          const Text(
            'Le navigateur peut demander une connexion séparée.',
            style: TextStyle(fontSize: 12),
          ),
        ],
      ),
    ),
  );
}

String clock(Duration value) =>
    '${value.inMinutes}:${(value.inSeconds % 60).toString().padLeft(2, '0')}';

class AudioLesson extends StatefulWidget {
  final String url;
  const AudioLesson({super.key, required this.url});
  @override
  State<AudioLesson> createState() => _AudioLessonState();
}

class _AudioLessonState extends State<AudioLesson> with WidgetsBindingObserver {
  final player = AudioPlayer();
  StreamSubscription<PlayerException>? errors;
  String? error;
  bool ready = false;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    errors = player.errorStream.listen((_) {
      if (mounted)
        setState(() => error = 'La lecture audio a été interrompue.');
    });
    load();
  }

  Future<void> load() async {
    try {
      if (Uri.parse(widget.url).scheme != 'https') throw Exception();
      await player.setUrl(widget.url).timeout(const Duration(seconds: 25));
      if (mounted) setState(() => ready = true);
    } catch (_) {
      if (mounted)
        setState(
          () => error =
              'Audio inaccessible. Vérifiez le fichier et son URL HTTPS.',
        );
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) player.pause();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    errors?.cancel();
    player.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          const Icon(Icons.headphones, size: 64, color: Color(0xff174d48)),
          const SizedBox(height: 12),
          const Text('Écouter et pratiquer'),
          if (error != null)
            Text(error!)
          else if (!ready)
            const Padding(
              padding: EdgeInsets.all(16),
              child: CircularProgressIndicator(),
            )
          else ...[
            StreamBuilder<Duration>(
              stream: player.positionStream,
              builder: (_, snapshot) {
                final duration = player.duration ?? Duration.zero;
                final position = snapshot.data ?? Duration.zero;
                final max = duration.inMilliseconds.toDouble();
                return Column(
                  children: [
                    Slider(
                      value: position.inMilliseconds.toDouble().clamp(
                        0.0,
                        max > 0 ? max : 1.0,
                      ),
                      max: max > 0 ? max : 1.0,
                      onChanged: max <= 0
                          ? null
                          : (value) => player.seek(
                              Duration(milliseconds: value.round()),
                            ),
                    ),
                    Text('${clock(position)} / ${clock(duration)}'),
                  ],
                );
              },
            ),
            StreamBuilder<PlayerState>(
              stream: player.playerStateStream,
              builder: (_, snapshot) {
                final playing = snapshot.data?.playing ?? false;
                final ended =
                    snapshot.data?.processingState == ProcessingState.completed;
                return IconButton.filled(
                  iconSize: 36,
                  icon: Icon(
                    playing && !ended ? Icons.pause : Icons.play_arrow,
                  ),
                  onPressed: () async {
                    try {
                      if (playing && !ended) {
                        await player.pause();
                      } else {
                        if (ended) await player.seek(Duration.zero);
                        await player.play();
                      }
                    } catch (_) {
                      if (mounted)
                        setState(() => error = 'Impossible de lire cet audio.');
                    }
                  },
                );
              },
            ),
          ],
        ],
      ),
    ),
  );
}

class VideoLesson extends StatefulWidget {
  final String url;
  const VideoLesson({super.key, required this.url});
  @override
  State<VideoLesson> createState() => _VideoLessonState();
}

class _VideoLessonState extends State<VideoLesson> with WidgetsBindingObserver {
  VideoPlayerController? player;
  String? error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
  }

  Future<void> load() async {
    try {
      final uri = Uri.parse(widget.url);
      if (uri.scheme != 'https') throw Exception();
      player = VideoPlayerController.networkUrl(uri)..addListener(update);
      await player!.initialize().timeout(const Duration(seconds: 25));
      if (mounted) setState(() {});
    } catch (_) {
      if (mounted)
        setState(
          () => error =
              'Vidéo inaccessible. Vérifiez le format et le lien HTTPS.',
        );
    }
  }

  void update() {
    if (mounted) setState(() {});
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) player?.pause();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    player?.removeListener(update);
    player?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = player;
    if (error != null || controller?.value.hasError == true)
      return Text(error ?? 'La lecture vidéo a été interrompue.');
    if (controller == null || !controller.value.isInitialized)
      return const Center(child: CircularProgressIndicator());
    return Column(
      children: [
        AspectRatio(
          aspectRatio: controller.value.aspectRatio,
          child: VideoPlayer(controller),
        ),
        VideoProgressIndicator(controller, allowScrubbing: true),
        IconButton(
          icon: Icon(
            controller.value.isPlaying ? Icons.pause : Icons.play_arrow,
          ),
          onPressed: () async {
            try {
              if (controller.value.isPlaying) {
                await controller.pause();
              } else {
                if (controller.value.position >= controller.value.duration)
                  await controller.seekTo(Duration.zero);
                await controller.play();
              }
            } catch (_) {
              if (mounted)
                setState(() => error = 'Impossible de lire cette vidéo.');
            }
          },
        ),
      ],
    );
  }
}
