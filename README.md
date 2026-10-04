# WB English — prototype 0.3.0 avec Mot du jour

**Nouveau :** mot publié dans WordPress, 1 à 3 audios enregistrés par l’élève, correction texte et/ou audio par le formateur, retour consultable dans l’app. Commencer par **TEST-MOT-DU-JOUR.md**. Les cours et quiz restent inclus ; leur test est décrit dans **TEST-QUIZ.md**.


Ce ZIP regroupe les sources Flutter Android/iOS, le connecteur WordPress, les tests et le workflow qui compile un APK Android de test. Aucun APK ou IPA déjà compilé n'est inclus.

## Envoyer sur GitHub

1. Décompresser le ZIP sur l'ordinateur.
2. Copier **tout le contenu** à la racine du dépôt `App-WBenglish`, en remplaçant les anciens fichiers. Inclure le dossier **`.github`**.
3. À la racine du dépôt, il faut voir `.github`, `flutter`, `scripts`, `tests`, `wordpress` et ce `README.md`. Ne pas ajouter un dossier parent autour de ces éléments.
4. Faire un commit sur `main`, puis pousser les changements si GitHub Desktop est utilisé.
5. Dans GitHub : **Actions → Construire APK Android**. Le push sur `main` déclenche le workflow. Il peut aussi être lancé avec **Run workflow** une fois présent sur la branche principale.
6. Après réussite, ouvrir le résultat puis **Artifacts → WB-English-APK-test**. Décompresser l'archive téléchargée pour obtenir `app-debug.apk`, à installer sur Android ou LDPlayer.

La compilation est configurée pour `https://wbenglish.deardevice.com`. Aucun identifiant élève ni mot de passe n'est intégré au dépôt. La connexion se fait dans l'app après configuration du plugin WordPress.

Le workflow génère les dossiers natifs avant l'analyse et la compilation. Les réglages d'analyse sont inclus pour éviter une référence à un paquet de lint absent lors de cette génération.

## Android et iOS

L'automatisation fournie compile l'APK Android de test. Les mêmes sources incluent les fonctionnalités prévues pour iOS ; la préparation génère aussi son projet natif. La compilation iOS et la signature Apple restent à réaliser sur macOS selon `LISEZ-MOI.md`. Ce lot ne fournit pas d'IPA signé.

## WordPress

Le plugin installable est dans `wordpress/wbenglish-mobile-bridge.zip`. Version 0.3.0 du connecteur : Mot du jour ajouté, compatibilité Grades des quiz conservée. Remplacer le connecteur installé. Le menu **WB — Mot du jour** permet de publier et corriger. Le réglage **Envoi des quiz** concerne uniquement les quiz. Configurer l'ID du compte élève et les IDs de cours, puis créer son mot de passe d'application WordPress. Voir `LISEZ-MOI.md`.

## Validation

Le YAML et l'intégrité de ce ZIP ont été vérifiés. La compilation sur GitHub n'a pas été exécutée ici. En cas d'étape rouge, ouvrir son journal et communiquer le message d'erreur. Les tests isolés du connecteur et les limites du premier prototype sont détaillés dans `VALIDATION.md`.
