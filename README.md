# Job Matcher

Application web personnelle destinée à agréger des offres d'emploi, les comparer à un profil candidat et produire un score de compatibilité expliqué.

Le projet avance par incréments. La version actuelle contient le dépôt privé et l'analyse asynchrone d'un CV, ainsi que l'ajout de recherches HelloWork et l'import asynchrone d'offres normalisées.

## Stack

- PHP 8.4 et Symfony 7.4 LTS
- Doctrine ORM et PostgreSQL 16
- Symfony Messenger avec Redis
- Twig et SCSS compilé par SassBundle et AssetMapper, sans chaîne Node
- PHPUnit 12, PHPStan et PHP-CS-Fixer
- Docker Compose et GitHub Actions

## Architecture visée

Le projet est un monolithe modulaire organisé autour de trois fonctionnalités : `Candidate`, `Job` et `Matching`. Les interfaces sont placées uniquement aux frontières changeantes, notamment les fournisseurs d'offres, les contrôles de disponibilité et le fournisseur LLM.

L’analyse de compatibilité visible est produite par le LLM à partir du texte complet de l’annonce et du CV. Le pourcentage reste toujours accompagné d’exigences dédupliquées, de preuves textuelles et de verdicts structurés afin de pouvoir être contrôlé.

## Installation

Prérequis : Docker avec le plugin Compose et `make`.

```bash
make setup
```

L'application est ensuite accessible sur <http://localhost:8080>. PostgreSQL et Redis sont exposés respectivement sur les ports `5433` et `6380` pour les outils locaux.

Ouvrez `/inscription` pour créer un compte. Chaque compte possède son propre profil, ses CV, ses recherches et ses analyses ; le reste de l'application est protégé par connexion email et mot de passe.

Pour activer « Continuer avec Google », créez un client OAuth Web dans Google Cloud, autorisez l'URI de retour `http://localhost:8080/connexion/google/retour`, puis définissez dans `.env.local` :

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

Un compte Google portant le même email est associé au compte local existant. Une nouvelle identité Google crée son propre espace personnel. La session authentifiée reste valide pendant 30 jours d’activité, sauf déconnexion volontaire.

## Commandes utiles

```bash
make up                # démarrer l'application et le worker
make down              # arrêter les conteneurs
make restart           # redémarrer PHP, le worker et nginx
make migrate           # appliquer les migrations Doctrine
make migration-status  # afficher l'état des migrations
make schema-validate   # comparer les entités et le schéma PostgreSQL
make logs              # suivre les logs
make shell             # ouvrir un shell dans le conteneur PHP
make sass              # compiler les fichiers SCSS
make test              # lancer PHPUnit
make qa                # style, PHPStan et PHPUnit
```

## Intégration continue

Le workflow GitHub Actions `.github/workflows/ci.yaml` s’exécute à chaque push et pull request. Il construit l’image PHP 8.4, prépare la base PostgreSQL de test, applique les migrations puis lance Sass, PHP-CS-Fixer, PHPStan et toute la suite PHPUnit avec `make qa`.

## Image de production

Le fichier `compose.prod.yaml` décrit une exécution sans montage du code source et sans exposition publique de PostgreSQL ou Redis. Deux cibles sont construites depuis le même `Dockerfile` : `php_prod`, utilisée par PHP-FPM et le worker, et `nginx_prod`, qui contient les fichiers publics compilés. Les CV privés résident dans un volume monté sur `/app/var/cv` et les sessions de production sont conservées dans Redis.

Avant toute extraction, le worker vérifie la signature et la structure du fichier. Un PDF doit posséder un en-tête, une fin et une table de références cohérents. Un DOCX doit être un paquet OpenXML non chiffré contenant ses composants obligatoires, sans chemin dangereux ni doublon. Les archives sont limitées à 1 000 entrées, 10 Mo par entrée et 50 Mo au total après décompression afin de bloquer les bombes ZIP.

Le worker transmet ensuite le fichier par flux à un service ClamAV local. Un fichier infecté est refusé définitivement avant extraction ; une indisponibilité de l’antivirus provoque une nouvelle tentative asynchrone. Le port ClamAV n’est jamais publié sur l’hôte et ses signatures sont conservées dans le volume `clamav_data`.

L’extraction par `pdftotext` ou `unzip` s’exécute dans un conteneur dédié, relié au worker uniquement par une socket Unix. Ce conteneur ne reçoit aucun fichier d’environnement de production, n’a aucun réseau, monte les CV en lecture seule et fonctionne avec toutes ses capacités Linux supprimées, `no-new-privileges`, un système de fichiers racine en lecture seule, 0,5 CPU, 128 Mo de mémoire et 32 PID au maximum. Le texte retourné est limité à 2 Mo.

Pour valider la configuration puis construire les deux images localement avec des valeurs factices :

```bash
make prod-config
make prod-build
```

Sur le serveur, copier `.env.prod.example` vers `.env.prod.local`, remplacer toutes les valeurs factices et limiter ses droits avec `chmod 600 .env.prod.local`. Ce fichier ne doit jamais être commité.

Les inscriptions locales exigent une vérification par email. Avant de les ouvrir en production, remplacer le transport neutre par le DSN SMTP du fournisseur et une adresse d'expédition valide :

```dotenv
MAILER_DSN=smtp://user:password@smtp.example.com:587
MAILER_FROM='Job Matcher <no-reply@example.com>'
```

Le DSN réel reste uniquement dans `.env.prod.local`. Les comptes créés avec Google utilisent déjà une adresse vérifiée par Google.

Le workflow manuel `.github/workflows/publish-images.yaml` construit et publie les deux cibles sur GitHub Container Registry. Dans GitHub, ouvrir **Actions → Publish production images → Run workflow**, puis conserver `latest` ou saisir une version. Chaque image reçoit également une étiquette immuable `sha-<commit>` :

```text
ghcr.io/sauvank/job-matcher-php
ghcr.io/sauvank/job-matcher-nginx
```

Le workflow utilise uniquement le `GITHUB_TOKEN` éphémère fourni par GitHub avec la permission `packages: write` ; aucun secret GHCR personnel n'est requis pour publier.

### Déploiement automatisé

Un push sur `main` lance les contrôles qualité, publie les images PHP et nginx portant le SHA immuable du commit, puis prépare un déploiement vers l'environnement GitHub `production`. Aucun nom d'hôte, port, utilisateur ou chemin du serveur n'est inscrit dans le dépôt.

Créer l'environnement **Settings → Environments → production**, limiter ses branches de déploiement à `main`, ajouter une approbation obligatoire, puis y enregistrer ces secrets :

- `VPS_HOST` : nom d'hôte ou adresse du serveur ;
- `VPS_PORT` : port SSH ;
- `VPS_USER` : utilisateur SSH dédié ;
- `VPS_DEPLOY_PATH` : chemin absolu du clone de production ;
- `VPS_SSH_PRIVATE_KEY` : clé privée SSH dédiée au déploiement ;
- `VPS_KNOWN_HOSTS` : ligne `known_hosts` du serveur, dont l'empreinte a été vérifiée avant enregistrement.

Le serveur conserve seul `.env.prod.local` et tous les secrets applicatifs ; son dossier de production n'a pas besoin d'être un clone Git. Le workflow transfère uniquement `compose.prod.yaml` et `scripts/deploy-production.sh` dans `.deploy/releases/<sha>`. Le script verrouille les déploiements concurrents, sauvegarde PostgreSQL dans `.deploy/backups`, applique les migrations, recrée les services applicatifs et contrôle `/health`. Si une étape échoue, il affiche dans les logs GitHub Actions l'état et les derniers logs de ClamAV et de l'extracteur avant de restaurer les images précédentes avec le manifeste Compose déjà en production. Une migration de schéma incompatible reste une opération à traiter manuellement depuis la sauvegarde.

Les messages asynchrones utilisent Redis. Après trois échecs avec délai exponentiel, ils sont conservés dans une file d'échec Doctrine.

## Import HelloWork

La page `/sources` permet d’ajouter plusieurs intitulés ou groupes de mots-clés, par exemple `Développeur PHP backend`, `Symfony` et `PHP`. Chaque intitulé crée une recherche HelloWork séparée en utilisant la localisation du profil, puis un message Messenger est envoyé au worker. Une URL identique réutilise la source existante au lieu de créer un doublon.

Le connecteur récupère la première page de résultats, limite chaque synchronisation à dix fiches et lit les données structurées `JobPosting` de chaque offre. Les offres sont normalisées puis mises à jour de façon idempotente à partir de l'identifiant HelloWork. Les recherches actives sont resynchronisées chaque jour à 04:00, heure de Paris. Une offre absente des résultats courants est marquée expirée seulement si sa date de validité est dépassée ou si HelloWork confirme sa disparition par une réponse 404/410 ; une erreur temporaire reste neutre.

Les résultats classés sont visibles sur `/jobs`. Chaque fiche détaille le score et les raisons qui l’expliquent. Pour calculer les scores d’offres importées avant l’ajout du moteur :

```bash
docker compose exec -T php php bin/console app:matches:refresh
```

Une première extraction déterministe complète les champs HelloWork incomplets à partir des sections « Environnement technique » et « Stack technique ». La vérification de disponibilité fera l’objet d’un prochain incrément.

## Analyse réelle du CV avec OpenAI ou Google Gemini

Sans configuration locale, l'application utilise volontairement un faux analyseur gratuit (`CV_ANALYZER=fake`). Pour activer l'analyse avec OpenAI ou Google Gemini, créer un fichier `.env.local` à partir de l'exemple :

```bash
cp .env.local.example .env.local
```

Puis configurer le fournisseur choisi dans `.env.local` :

- **Pour Google Gemini** :
  ```dotenv
  CV_ANALYZER=gemini
  GEMINI_API_KEY=replace-with-your-gemini-api-key
  GEMINI_MODEL=gemini-2.0-flash
  JOB_GEMINI_MODEL=gemini-2.0-flash
  ```

- **Pour OpenAI** :
  ```dotenv
  CV_ANALYZER=openai
  OPENAI_API_KEY=replace-with-your-project-api-key
  OPENAI_MODEL=gpt-4.1-mini
  JOB_OPENAI_MODEL=gpt-5.6-luna
  ```

Puis redémarrer les processus :

```bash
make restart
```

Le fichier `.env.local` est ignoré par Git et ne doit jamais être commité. Sur la fiche d'un CV précédemment analysé en mode `fake`, utiliser le bouton **Relancer l'analyse**.

Le texte extrait du CV est envoyé à l'API Responses avec `store: false`. La réponse est contrainte par un schéma JSON strict puis présentée à l'utilisateur pour vérification ; elle n'est jamais appliquée automatiquement au profil.

La validation présente les compétences détectées sous forme de cartes avec leur catégorie et leur niveau proposé. Après application, la section **Mes compétences** du profil permet d'ajouter une compétence manuellement, de filtrer la liste par niveau, de modifier plusieurs niveaux puis de les enregistrer en une fois grâce à une barre d’action persistante, ou de supprimer une compétence.

Chaque compte utilise son propre profil candidat actif. Plusieurs CV peuvent être analysés et conservés dans son historique, mais appliquer un CV remplace les informations et les compétences de ce profil. Une ancienne analyse peut être réutilisée depuis sa fiche sans appeler à nouveau l’IA.

## Analyse complète des offres

À chaque import, toute nouvelle annonce est automatiquement envoyée au worker avec son texte complet utile, ses données `JobPosting`, les compétences validées et le texte du CV. Une annonce déjà analysée est automatiquement réanalysée si son contenu change, mais pas lors d’une synchronisation strictement identique. OpenAI retourne un JSON strict contenant un unique `compatibilityScore`, un résumé et toutes les exigences classées par catégorie, priorité et verdict. Chaque exigence doit citer une preuve issue de l’annonce et, lorsqu’elle existe, une preuve issue du CV.

Le seul pourcentage affiché dans `/jobs` et sur la fiche est celui retourné par l’IA. Une offre sans analyse affiche **À analyser**. Les contrôles déterministes restent internes et servent à normaliser les informations du fournisseur, notamment une durée d’expérience incohérente ou une stack absente du champ `skills`.

Le rapport agrégé **Optimiser mon CV** dispose de sa propre page `/profile/optimisation-cv`, accessible depuis la navigation principale.

Le modèle des offres est configurable séparément avec `JOB_OPENAI_MODEL`. La valeur par défaut est `gpt-5.6-luna`, choisie pour limiter le coût. La commande suivante permet également de mettre en file les anciennes correspondances qui n’ont pas encore été analysées, ou de relancer certains identifiants :

```bash
docker compose exec -T php php bin/console app:matches:analyze 18 26
```

## Décisions

- Symfony 7.4 LTS privilégie la stabilité tout en restant compatible avec PHP 8.4.
- Redis est utilisé pour les traitements asynchrones, les verrous d'idempotence et l'état persistant de l'ordonnanceur quotidien.
- La file d'échec reste dans PostgreSQL afin d'être facilement inspectable.
- L'interface reste en Twig/CSS pour limiter le coût de la V1.
- Les entités Doctrine feront partie du domaine : un second modèle de persistance serait superflu ici.

## Limites actuelles

- Les PDF contenant du texte et les DOCX sont pris en charge. Un PDF constitué uniquement d'images nécessite encore un module OCR.
- L'analyse OpenAI implique l'envoi du texte du CV et de l’annonce à un fournisseur externe et génère un coût d'API. Les requêtes utilisent `store: false`.
- Seule la première page d'une recherche HelloWork est importée, avec un maximum de dix fiches par synchronisation.
- Un score IA reste une aide à la décision : les preuves et les éléments `UNKNOWN` doivent être relus avant de candidater.
- L'application utilise un compte propriétaire unique ; elle ne fournit pas encore de récupération de mot de passe.

## Roadmap

1. filtres du dashboard Twig ;
2. pagination des sources ;
3. préférences candidat : salaire minimum, contrats et télétravail.
