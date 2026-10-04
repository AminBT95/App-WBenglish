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

## Connecteur 0.1.1

Le compte élève peut être renseigné par ID ou identifiant WordPress. Les comptes introuvables ou privilégiés sont refusés avec une explication et les réglages précédents sont conservés. Aucun contrôle d’accès n’a été supprimé. Les 12 tests d’accès et 9 tests isolés de validation des réglages réussissent. Le test d’intégration sur le site reste à effectuer.

## Version 0.2.0 — quiz

- 17 tests isolés du quiz réussis avec la vraie classe STM_LMS_Quiz du plugin fourni et une simulation des transactions WordPress : notation, anti-doublon, réponses invalides, refus d'accès, annulation d'écriture partielle, changement du quiz, limites de tentatives.
- 12 tests d'accès existants et 9 tests de réglages repassés avec succès.
- Syntaxe PHP des deux fichiers du connecteur validée. Sources et nouveaux tests Dart parsés et formatés hors ligne.
- Deux tests de widgets ajoutés au workflow de compilation : formulaire incomplet refusé, conservation de l'identifiant au nouvel envoi et résultat issu du serveur. Leur exécution dépend du prochain build GitHub.
- Aucun envoi de quiz effectué sur le site réel, aucun APK de cette version compilé ici.
- Exécuter les tests de quiz avec `php tests/quiz_submission.php /chemin/masterstudy-lms-learning-management-system`. Les sources tierces MasterStudy ne sont pas incluses dans cette archive.
