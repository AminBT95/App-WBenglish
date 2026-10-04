# État de validation — 4 octobre 2026

## Réalisé

- Lecture du code MasterStudy fourni : version gratuite 3.7.52 et Pro 4.7.9.
- Vérification en lecture seule de `https://wbenglish.deardevice.com/wp-json/` : HTTP 200 ; espaces `masterstudy-lms/v2`, `stm-lms/v1` et `lms` présents ; connecteur WB English absent.
- Analyse syntaxique du connecteur avec grammaire PHP 7.4 : réussie.
- Vérification syntaxique avec PHP WebAssembly : réussie.
- Exécution de 12 tests PHP isolés de contrôle d'accès : tous réussis. Cas : session cookie seule, compte autorisé, autre élève, administrateur, HTTP, leçon accessible, leçon d'un autre cours, verrouillage progressif, refus MasterStudy, absence d'inscription, durée limitée, leçon privée.
- Parsing et formatage des trois fichiers Dart (`lib/api.dart`, `lib/main.dart`, `test/pilot_test.dart`) avec le formateur Dart : réussis.

## Non validé

- Les tests PHP utilisent des doublures de WordPress et MasterStudy. Ils ne prouvent pas l'intégration réelle avec le site ni ses extensions personnalisées.
- L'analyse sémantique Flutter, la résolution complète des dépendances et les tests de widgets n'ont pas été exécutés jusqu'au bout. La préparation de Flutter a été bloquée par le contrôle automatique de l'environnement après une tentative d'accès de l'outil aux métadonnées de l'environnement. Aucun contournement de ce blocage n'a été tenté.
- Les dossiers natifs Android/iOS ne sont pas générés dans le livrable. `scripts/prepare_flutter.py` doit les générer sur la machine de développement et lancer l'analyse.
- Aucun APK ni IPA compilé, aucune signature et aucune publication.
- Aucune connexion avec un élève réel, aucune lecture audio/vidéo sur téléphone, aucune écriture sur le WordPress de production.

## Commandes à exécuter sur la machine de développement

```sh
php tests/bridge_access.php
python3 scripts/prepare_flutter.py
cd flutter
flutter test
flutter run --dart-define=WP_BASE_URL=https://VOTRE-SITE-DE-TEST
```

Les versions des dépendances seront résolues au premier `flutter pub get` ; conserver alors le fichier `pubspec.lock` obtenu. Les contraintes de versions proposées ne constituent pas une combinaison compilée et validée ici.

## Lot complet avec GitHub Actions

- Ajout de `.github/workflows/build-android.yml` à la racine du ZIP : compilation Android au push sur main et lancement manuel.
- Ajout des options d'analyse Dart standard et préservation du code, des tests et de ces options lors de la génération des projets natifs.
- Vérification de la syntaxe YAML, des chemins nécessaires, de la syntaxe Python et de l'intégrité de l'archive.
- Aucune nouvelle exécution Flutter locale, aucun lancement de GitHub Actions : compilation complète toujours à valider.
