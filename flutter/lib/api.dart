import 'dart:async';
import 'dart:convert';
import 'dart:io';

class ApiFailure implements Exception {
  final String message;
  ApiFailure(this.message);
  @override
  String toString() => message;
}

class Api {
  static final origin = Uri.parse(
    const String.fromEnvironment(
      'WP_BASE_URL',
      defaultValue: 'https://wbenglish.deardevice.com',
    ),
  );
  String? _authorization;
  void login(String username, String password) {
    if (username.trim().isEmpty ||
        username.contains(':') ||
        password.trim().isEmpty) {
      throw ApiFailure(
        'Saisis le compte élève et son mot de passe d’application.',
      );
    }
    _authorization =
        'Basic ${base64Encode(utf8.encode('${username.trim()}:${password.replaceAll(' ', '')}'))}';
  }

  void logout() => _authorization = null;
  Future<Map<String, dynamic>> get(String route) async {
    if (origin.scheme != 'https' ||
        origin.host.isEmpty ||
        origin.userInfo.isNotEmpty ||
        origin.hasQuery ||
        origin.hasFragment) {
      throw ApiFailure(
        'Le site doit utiliser une URL HTTPS sans identifiants.',
      );
    }
    final client = HttpClient()
      ..connectionTimeout = const Duration(seconds: 15);
    try {
      final base = origin.toString().replaceFirst(RegExp(r'/+$'), '');
      final req = await client.getUrl(
        Uri.parse('$base/wp-json/wbenglish-mobile/v1$route'),
      );
      req.followRedirects =
          false; // Never forward Basic credentials to a redirected host.
      req.headers.set(HttpHeaders.acceptHeader, 'application/json');
      if (_authorization != null)
        req.headers.set(HttpHeaders.authorizationHeader, _authorization!);
      final response = await req.close().timeout(const Duration(seconds: 20));
      final bytes = <int>[];
      await for (final chunk in response.timeout(const Duration(seconds: 20))) {
        bytes.addAll(chunk);
        if (bytes.length > 2 * 1024 * 1024)
          throw ApiFailure('Réponse trop volumineuse.');
      }
      if (response.statusCode >= 300 && response.statusCode < 400) {
        throw ApiFailure(
          'Le site redirige la requête. Vérifier son URL HTTPS canonique.',
        );
      }
      Map<String, dynamic> data;
      try {
        data = jsonDecode(utf8.decode(bytes)) as Map<String, dynamic>;
      } catch (_) {
        throw ApiFailure(
          'Réponse non JSON : vérifier le plugin et les permaliens WordPress.',
        );
      }
      if (response.statusCode != 200) {
        throw ApiFailure(
          data['message'] is String
              ? data['message'] as String
              : 'Accès refusé (${response.statusCode}).',
        );
      }
      return data;
    } on SocketException {
      throw ApiFailure('Connexion au site impossible.');
    } on HandshakeException {
      throw ApiFailure('Le certificat HTTPS du site est invalide.');
    } on TimeoutException {
      throw ApiFailure('Le site met trop de temps à répondre. Réessaie.');
    } finally {
      client.close(force: true);
    }
  }
}
