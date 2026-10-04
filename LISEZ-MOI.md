# WB English — pilote mobile 0.1

Ce dossier contient un premier prototype Flutter destiné à Android et iOS et un plugin de connexion séparé pour WordPress. Il utilise les cours et inscriptions MasterStudy existants. Ce n'est pas une application prête à publier.

## Ce que contient ce premier test

- Connexion d'un élève dédié avec un **mot de passe d'application WordPress**.
- Liste des cours sélectionnés et accessibles à cet élève.
- Programme du cours, leçons verrouillées et état de complétion existant.
- Texte simplifié ; lecture audio et vidéo pour les fichiers et liens directs HTTPS.
- Lecture, pause et déplacement dans le média. L'audio se met en pause lorsque l'application passe en arrière-plan.
- Ouverture du site pour les activités non prises en charge. Le navigateur peut demander une connexion distincte.

La connexion normale des clients, les inscriptions, achats, quiz natifs, notifications, téléchargement hors ligne, audio en arrière-plan et écriture de progression ne sont pas réalisés dans cette version. Les leçons audio intégrées via iframe/shortcode et les lecteurs vidéo YouTube/Vimeo/DRM nécessitent une intégration supplémentaire. Les images, tableaux et mises en forme du texte ne sont pas encore reproduits.

## 1. Préparer WordPress

Commencer sur une copie de test du site, avec des cours de démonstration. Le code a été préparé à partir de MasterStudy gratuit **3.7.52** et Pro **4.7.9** fournis ; sa compatibilité doit être validée sur l'installation.

1. Dans **Extensions → Ajouter une extension → Téléverser**, installer `wbenglish-mobile-bridge.zip`, puis activer.
2. Créer un **compte élève dédié**, sans droits d'administration, d'édition ou de formateur. L'inscrire manuellement à un cours de test publié contenant une leçon texte et une leçon audio MP3 HTTPS.
3. Relever l'ID numérique de l'élève dans l'URL de son édition (`user_id=…`) et celui du cours (`post=…`).
4. Dans **Réglages → WB English Mobile**, saisir cet ID élève et les IDs des cours autorisés, séparés par des virgules ; enregistrer.
5. Dans le profil WordPress de cet élève, créer un **mot de passe d'application**, nommé par exemple « WB English pilote ». Le conserver localement pour la saisie dans l'app. Ne pas envoyer de mot de passe administrateur dans la conversation.
6. Vérifier dans le navigateur : `https://VOTRE-SITE/wp-json/wbenglish-mobile/v1/status`. Une réponse JSON avec `bridge: 0.1.0` doit apparaître.

Le pilote est désactivé tant qu'aucun élève n'est sélectionné. Il n'accorde aucune inscription. Il refuse les cours privés, protégés par mot de passe, à durée limitée ou « bientôt disponibles ». Les abonnements ne sont pas couverts : si le système d'abonnement est actif, ce pilote peut bloquer tous les cours. Ne pas désactiver les protections commerciales du site de production pour contourner ce refus.

Le connecteur appelle le contrôle d'accès MasterStudy avec l'auto-inscription désactivée et vérifie les règles de déblocage des leçons. Des extensions personnalisées peuvent imposer d'autres règles : à vérifier avant utilisation réelle. Il ne modifie ni les fichiers MasterStudy, ni les cours, ni la progression. WordPress peut mettre à jour ses traces d'utilisation des mots de passe d'application.

Si le serveur renvoie 401 malgré des identifiants corrects, vérifier que l'hébergement transmet l'en-tête `Authorization`, que les mots de passe d'application sont activés et que HTTPS est correctement reconnu par WordPress derrière son proxy. Ne pas désactiver globalement le pare-feu ni la vérification TLS.

### Vérifier la connexion sans compiler l’app

Si Python 3 est installé sur votre ordinateur, exécuter depuis ce dossier :

```sh
python3 scripts/test_connection.py
```

Le script demande localement l’URL, l’identifiant élève et le mot de passe d’application (saisie masquée), puis compte les cours et leçons audio accessibles. Il ne modifie pas WordPress.

## 2. Préparer Flutter

Prérequis : Flutter stable récent avec Dart ≥ 3.6, Python 3, Android Studio/SDK pour Android. Pour iOS : un Mac, Xcode et les dépendances iOS demandées par `flutter doctor`.

Depuis ce dossier :

```sh
flutter doctor
python3 scripts/prepare_flutter.py
cd flutter
flutter run --dart-define=WP_BASE_URL=https://VOTRE-SITE-DE-TEST
```

Le script génère les dossiers standards Android et iOS avec le Flutter installé, ajoute la permission Internet Android, conserve le code applicatif fourni, résout les dépendances et lance l'analyse statique. L'URL par défaut est `https://wbenglish.deardevice.com`. Pour une copie de test, fournir explicitement `WP_BASE_URL` comme ci-dessus.

Dans l'app : saisir l'identifiant de l'élève et son mot de passe d'application. Les identifiants restent en mémoire pour la session ; ils ne sont pas enregistrés sur le téléphone, ni envoyés aux serveurs de médias. Se déconnecter à la fin du test.

### Android

```sh
flutter build apk --debug --dart-define=WP_BASE_URL=https://VOTRE-SITE-DE-TEST
```

Le fichier attendu est `build/app/outputs/flutter-apk/app-debug.apk`. Il s'agit d'un APK de test, pas d'une version boutique.

### iOS, sur Mac

```sh
flutter run --dart-define=WP_BASE_URL=https://VOTRE-SITE-DE-TEST
```

Choisir le simulateur ou l'iPhone. Pour l'appareil et TestFlight, configurer l'équipe Apple, l'identifiant de bundle propre au client et la signature dans `ios/Runner.xcworkspace`. La même source Dart sert aux deux plateformes ; iOS nécessite sa compilation et ses tests propres. Aucun fichier IPA signé n'est fourni dans ce lot.

## 3. Scénario de validation

1. `/status` répond sans identifiants ; `/courses` refuse une requête anonyme.
2. Un mauvais mot de passe, un autre élève et un administrateur sont refusés.
3. L'élève autorisé voit uniquement les cours sélectionnés auxquels il est inscrit.
4. Une leçon d'un autre cours, une leçon privée ou une leçon encore verrouillée est refusée même en demandant directement son URL API.
5. Une leçon audio MP3 HTTPS permet lecture/pause/déplacement sur Android puis iPhone.
6. Une vidéo MP4 HTTPS et une leçon texte s'affichent ; un lecteur intégré non pris en charge propose le site.
7. La progression WordPress reste inchangée après lecture.
8. Après révocation du mot de passe d'application ou mise de l'ID élève à 0, les nouvelles requêtes sont refusées.

Les fichiers de média déjà chargés peuvent rester lisibles dans le lecteur jusqu'à fermeture. Cette API ne transforme pas les fichiers publics WordPress en stockage privé et n'ajoute pas de DRM. Utiliser des médias de test pour ce pilote.

## API fournie

Toutes les routes sont en GET sous `/wp-json/wbenglish-mobile/v1` :

| Route | Usage |
|---|---|
| `/status` | Présence du connecteur, sans authentification |
| `/courses` | Cours de l'élève autorisé |
| `/courses/123` | Programme d'un cours autorisé |
| `/courses/123/lessons/456` | Contenu d'une leçon accessible de ce cours |

L'authentification est gérée par WordPress via ses mots de passe d'application sur HTTPS. Aucun nouvel algorithme de mot de passe ou contournement de connexion n'est ajouté.

## Retirer le pilote

Révoquer le mot de passe d'application de test, remettre l'ID élève à 0 puis désactiver/supprimer le connecteur. L'option de configuration `wbenglish_mobile_pilot` reste en base après désinstallation ; elle ne contient que l'ID élève et les IDs de cours, aucun secret.

## Références techniques

- https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- https://pub.dev/packages/just_audio
- https://pub.dev/packages/video_player
- https://docs.flutter.dev/get-started/install

Le ZIP des plugins MasterStudy fourni par le client n'est pas redistribué dans ce livrable.
