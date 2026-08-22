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

Au premier accès, ouvrez `/inscription` pour créer l'unique compte propriétaire. Le reste de l'application est ensuite protégé par connexion email et mot de passe.

Pour activer « Continuer avec Google », créez un client OAuth Web dans Google Cloud, autorisez l'URI de retour `http://localhost:8080/connexion/google/retour`, puis définissez dans `.env.local` :

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

Un compte Google portant le même email est associé au compte local existant. Comme l'application utilise un profil personnel unique, toute autre identité est refusée.

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

Les messages asynchrones utilisent Redis. Après trois échecs avec délai exponentiel, ils sont conservés dans une file d'échec Doctrine.

## Import HelloWork

La page `/sources` permet d’ajouter plusieurs intitulés ou groupes de mots-clés, par exemple `Développeur PHP backend`, `Symfony` et `PHP`. Chaque intitulé crée une recherche HelloWork séparée en utilisant la localisation du profil, puis un message Messenger est envoyé au worker. Une URL identique réutilise la source existante au lieu de créer un doublon.

Le connecteur récupère la première page de résultats, limite chaque synchronisation à dix fiches et lit les données structurées `JobPosting` de chaque offre. Les offres sont normalisées puis mises à jour de façon idempotente à partir de l'identifiant HelloWork.

Les résultats classés sont visibles sur `/jobs`. Chaque fiche détaille le score et les raisons qui l’expliquent. Pour calculer les scores d’offres importées avant l’ajout du moteur :

```bash
docker compose exec -T php php bin/console app:matches:refresh
```

Une première extraction déterministe complète les champs HelloWork incomplets à partir des sections « Environnement technique » et « Stack technique ». La vérification de disponibilité fera l’objet d’un prochain incrément.

## Analyse réelle du CV avec OpenAI

Sans configuration locale, l'application utilise volontairement un faux analyseur gratuit. Pour activer l'analyse OpenAI, créer un fichier `.env.local` à partir de l'exemple :

```bash
cp .env.local.example .env.local
```

Puis remplacer la valeur factice de `OPENAI_API_KEY` par une clé API de projet et redémarrer les processus :

```bash
make restart
```

Le fichier `.env.local` est ignoré par Git et ne doit jamais être commité. Sur la fiche d'un CV précédemment analysé en mode `fake`, utiliser le bouton **Relancer l'analyse**.

Le texte extrait du CV est envoyé à l'API Responses avec `store: false`. La réponse est contrainte par un schéma JSON strict puis présentée à l'utilisateur pour vérification ; elle n'est jamais appliquée automatiquement au profil.

La V1 utilise un seul profil candidat actif. Plusieurs CV peuvent être analysés et conservés dans l’historique, mais appliquer un CV remplace les informations et les compétences du profil actif. Une ancienne analyse peut être réutilisée depuis sa fiche sans appeler à nouveau l’IA.

## Analyse complète des offres

À chaque import, toute nouvelle annonce est automatiquement envoyée au worker avec son texte complet utile, ses données `JobPosting`, les compétences validées et le texte du CV. Une annonce déjà analysée est automatiquement réanalysée si son contenu change, mais pas lors d’une synchronisation strictement identique. OpenAI retourne un JSON strict contenant un unique `compatibilityScore`, un résumé et toutes les exigences classées par catégorie, priorité et verdict. Chaque exigence doit citer une preuve issue de l’annonce et, lorsqu’elle existe, une preuve issue du CV.

Le seul pourcentage affiché dans `/jobs` et sur la fiche est celui retourné par l’IA. Une offre sans analyse affiche **À analyser**. Les contrôles déterministes restent internes et servent à normaliser les informations du fournisseur, notamment une durée d’expérience incohérente ou une stack absente du champ `skills`.

Le modèle des offres est configurable séparément avec `JOB_OPENAI_MODEL`. La valeur par défaut est `gpt-5.6-luna`, choisie pour limiter le coût. La commande suivante permet également de mettre en file les anciennes correspondances qui n’ont pas encore été analysées, ou de relancer certains identifiants :

```bash
docker compose exec -T php php bin/console app:matches:analyze 18 26
```

## Décisions

- Symfony 7.4 LTS privilégie la stabilité tout en restant compatible avec PHP 8.4.
- Redis est utilisé pour les traitements asynchrones et les futurs verrous d'idempotence.
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

1. contrôle de disponibilité ;
2. filtres du dashboard Twig ;
3. pagination des sources et planification quotidienne.
