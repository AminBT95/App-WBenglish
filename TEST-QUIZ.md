# Test aller-retour app → WordPress → app — connecteur 0.2.1

## Installation

1. Remplacer le plugin WordPress par `wbenglish-mobile-bridge.zip` version **0.2.1**.
2. Dans Réglages → WB English Mobile, garder le même compte élève et le même cours autorisé. Cocher **Envoi des quiz**, puis enregistrer.
3. Mettre le contenu du ZIP complet à la racine du dépôt GitHub, y compris `.github`, et pousser sur `main`.
4. Dans GitHub Actions, attendre la réussite de la nouvelle compilation, télécharger `WB-English-APK-test`, puis installer le nouvel APK. La version de l'app est **0.2.0+2**. Le mot de passe d'application WordPress reste le même.

## Préparer le quiz

Créer un quiz dans le programme d'un **cours de test**, auquel le compte élève est déjà inscrit. Le cours doit rester dans la liste autorisée du connecteur.

- Titre : `Test synchronisation mobile`.
- Seuil de réussite : **60 %**.
- Aucun chronomètre ; aucun ordre aléatoire des questions ou réponses.
- Pas de banque de questions, H5P, questions à images ou questions obligatoires spécifiques.
- Pour faciliter les essais : tentatives illimitées, reprise après réussite autorisée et pénalité de reprise à **0 %**.
- Publier le quiz et ses questions. Le module Grades peut rester actif. Le score du quiz reste enregistré en pourcentage ; son hook natif recalcule la note du cours. La conversion en lettre/points n’est pas affichée dans l’app.

| Question | Type | Propositions | Bonne réponse |
|---|---|---|---|
| Que signifie « Hello » ? | Choix unique | Bonjour ; Au revoir | Bonjour |
| Quels mots désignent des fruits ? | Choix multiples | Apple ; Pear ; Car | Apple et Pear |
| « Cat » signifie « chien ». | Vrai/faux | True ; False | False |

## Vérifier l'enregistrement

1. Dans l'app, se connecter avec le compte élève, ouvrir le cours puis le quiz.
2. Répondre **Bonjour**, **Apple et Pear**, **False**.
3. Toucher **Valider mes réponses**, puis **Enregistrer**. Cette opération crée une vraie tentative dans MasterStudy.
4. Vérifier le résultat attendu : **100 %, quiz réussi** et un numéro de tentative MasterStudy.
5. Toucher **Relire depuis WordPress** : le même résultat doit être renvoyé par le serveur.
6. Dans MasterStudy, consulter les tentatives du même élève, du même cours et du même quiz. Vérifier le score, les trois réponses, l'heure et la progression du cours. Le numéro affiché par l'app correspond à l'identifiant de ligne de tentative ; l'interface MasterStudy peut afficher un numéro ordinal différent.
7. Refaire le quiz avec **Au revoir**, **Apple et Pear**, **False** : avec les réglages ci-dessus, résultat attendu **67 %, réussi**, avec une nouvelle tentative.
8. Dans le programme de l'app, tirer la liste vers le bas ou rouvrir le cours pour recharger la progression.

Les résultats d'essai restent en base. Aucune suppression automatique de tentative n'est effectuée. Les hooks de progression MasterStudy sont déclenchés après enregistrement et les extensions du site peuvent exécuter leurs effets habituels. Les notifications email spécifiques du contrôleur web de quiz ne sont pas reproduites par le connecteur.

## Coupure de réseau

Le bouton **Réessayer le même envoi** conserve le même identifiant de requête et les mêmes réponses tant que cet écran reste ouvert. Une réponse perdue après un enregistrement confirmé ne crée pas une nouvelle tentative lors de ce nouvel envoi. En cas de fermeture de l'app, rouvrir le quiz et vérifier le dernier résultat avant de répondre de nouveau. Ne pas passer simultanément le même quiz avec ce compte dans le navigateur pendant ce premier test.

## Limites et contrôles

- Quiz natifs à choix unique, choix multiples et vrai/faux avec réponses textuelles, 1 à 50 questions.
- La lecture du quiz n'enregistre pas une tentative commencée ; la tentative et les réponses sont enregistrées au moment de la validation. Les quiz chronométrés sont refusés dans cette version.
- Le serveur contrôle le compte autorisé, l'inscription, le cours du quiz, son déblocage, les options choisies, les limites de tentatives et la reprise après réussite.
- La note est calculée par le moteur `STM_LMS_Quiz::check_answer`. Le client n'envoie ni note ni indicateur de bonne réponse.
- Une modification du quiz pendant sa saisie oblige à rouvrir le formulaire.
- Les tables MasterStudy des réponses et tentatives et la table WordPress des options doivent utiliser InnoDB. Une transaction enregistre ensemble les réponses, le score et le reçu du dernier envoi. Le serveur doit autoriser les verrous MySQL `GET_LOCK` et la consultation du moteur des tables. Le pilote refuse l'écriture si ces conditions ne sont pas réunies.
- Le reçu du dernier envoi est conservé dans une option non chargée automatiquement, propre au compte/cours/quiz. Il ne contient aucun mot de passe.
- Les 21 tests isolés du quiz passent avec la vraie classe de notation fournie dans MasterStudy. La base WordPress est simulée dans ces tests ; le test d'intégration réel reste celui décrit ci-dessus.
