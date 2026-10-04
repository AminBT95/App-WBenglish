# Tester le Mot du jour — version 0.3.0

Cette version ajoute un module de pratique orale au prototype existant. Les cours et quiz restent disponibles. Le module utilise les comptes WordPress et les accès aux cours MasterStudy ; ses réponses sont distinctes des notes de quiz MasterStudy.

## 1. Remplacer le plugin

Dans WordPress : **Extensions → Ajouter une extension → Téléverser**.
Choisir `wbenglish-mobile-bridge.zip`, puis **Remplacer l’extension actuelle** si proposé. Ne pas désinstaller l’ancien plugin pour cette mise à jour. Les réglages existants sont conservés.

Vérifier `https://wbenglish.deardevice.com/wp-json/wbenglish-mobile/v1/status` : `bridge` doit être `0.3.0`.

## 2. Publier le premier mot

Dans l’administration WordPress, en HTTPS : **WB — Mot du jour**.

- Mot : **Journey**.
- Consigne : **Journey signifie voyage / trajet. Exemple : My journey to school takes twenty minutes. Enregistrez deux phrases personnelles avec ce mot.**
- Date : aujourd’hui (fuseau horaire défini dans WordPress).
- Cours : un cours autorisé dans **Réglages → WB English Mobile** et auquel l’élève de test est déjà inscrit.
- Cliquer **Publier**.

L’administrateur peut publier et corriger immédiatement. Pour utiliser un autre compte formateur : saisir son **identifiant WordPress** en haut de cette page et enregistrer. Ce compte verra alors **WB — Mot du jour** après connexion. Ne pas lui donner les droits administrateur. Un seul formateur est désigné dans ce pilote et il peut traiter tous les mots du module ; le compte élève sélectionné ne peut pas être ce formateur.

## 3. Compiler et installer la nouvelle app

Décompresser `wbenglish-mobile-complet.zip`. Remplacer le contenu du dépôt GitHub avec **tout son contenu**, y compris `.github`, sans ajouter de dossier parent.

Faire un commit sur `main`, puis ouvrir **Actions → Construire APK Android**. Une fois la compilation réussie, télécharger **Artifacts → WB-English-APK-test**, extraire et installer `app-debug.apk`.

L’ancien APK n’a pas l’écran Mot du jour : il faut cette nouvelle compilation. Aucun APK précompilé n’est inclus dans ce ZIP.

## 4. Envoyer les audios élève

1. Se connecter dans l’app avec le compte élève de test et son mot de passe d’application habituel.
2. Ouvrir **Mot du jour**, puis **Journey**.
3. Appuyer sur **Enregistrer une phrase** et autoriser le microphone.
4. Prononcer une phrase, puis **Arrêter**. Écouter le résultat.
5. Enregistrer une deuxième phrase si souhaité.
6. Appuyer sur **Envoyer au formateur**, puis confirmer.
7. Vérifier que l’app affiche **Réponse envoyée** et permet de réécouter les phrases.

Limites du pilote : 1 à 3 fichiers par réponse, 60 secondes et 1 Mo maximum par fichier. Une seule réponse élève par mot, non modifiable après envoi. Une nouvelle tentative après une coupure réseau réutilise le même identifiant d’envoi. Les brouillons locaux sont supprimés en quittant la page ; ils ne sont pas des devoirs enregistrés hors ligne.

Utiliser de préférence un vrai téléphone pour vérifier le microphone. Sur émulateur, la capture dépend aussi des réglages audio de l’ordinateur et de l’émulateur.

## 5. Corriger dans WordPress

1. Ouvrir **WB — Mot du jour**.
2. Sous **Journey**, cliquer **Ouvrir les réponses et corriger**.
3. Écouter les enregistrements élève.
4. Ajouter un commentaire, par exemple : « Bonne phrase. Attention à la prononciation de journey. »
5. Facultativement, **Enregistrer la correction**, autoriser le micro du navigateur, parler puis **Arrêter**. Un fichier audio existant peut aussi être choisi.
6. Cliquer **Enregistrer la correction**.
7. Dans l’app, revenir sur le mot et appuyer sur **Actualiser**. Vérifier texte et lecture de la correction.

Le formateur peut modifier sa correction. Aucune notification push n’est encore envoyée. L’enregistrement du navigateur produit un WAV mono adapté à la voix ; les fichiers M4A/MP3 peuvent être utilisés pour une meilleure qualité. Formats de fichiers acceptés après analyse : M4A, MP3, WAV, OGG et WebM audio (durée détectable obligatoire). Un fichier vidéo est refusé.

## Conservation et accès

Les fichiers du module sont stockés dans des options WordPress non autoloadées, sans pièce jointe publique ni URL publique dans `uploads`. La lecture côté app exige à nouveau l’authentification et l’accès au cours. La correction est réservée au formateur désigné et aux administrateurs. Les administrateurs du serveur et les sauvegardes de la base ont naturellement accès à ces données.

Pilote limité à 30 mots. Pour supprimer un test et ses réponses, un administrateur ouvre les réponses du mot puis utilise **Supprimer ce mot et ses réponses**. La désactivation du plugin ne supprime pas ces données. Avant un déploiement multiélève, prévoir stockage privé adapté, quotas globaux, rétention/purge et gestion fine de plusieurs formateurs. Le pilote conserve le fonctionnement avec un seul compte élève sélectionné.

## Validation restant à faire sur votre installation

- Parcours complet ci-dessus, redémarrage de l’app puis relecture de la correction.
- Refus de permission micro, envoi interrompu puis nouvelle tentative.
- Vérification que le formateur désigné voit le menu et que l’élève ne le voit pas.
- Date future : le mot ne doit pas encore apparaître dans l’app.
- Retrait de l’accès au cours : le mot et les fichiers doivent devenir inaccessibles.
- Tests sur iPhone après compilation et signature sur Mac. Sources et permission micro iOS incluses, mais aucun IPA signé ni test appareil iOS effectué ici.

Les contrôles PHP isolés ont été exécutés ; la compilation Flutter et les échanges réels sur votre WordPress restent à valider avec ce lot. Le code ne remplace pas les fichiers de MasterStudy.
