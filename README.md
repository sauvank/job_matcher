# Job Matcher

Application web personnelle destinée à agréger des offres d'emploi, les comparer à un profil candidat et produire un score de compatibilité expliqué.

Le projet avance par incréments. La version actuelle contient le dépôt privé et l'analyse asynchrone d'un CV, ainsi que l'ajout de recherches HelloWork et l'import asynchrone d'offres normalisées.

## Stack

- PHP 8.4 et Symfony 7.4 LTS
- Doctrine ORM et PostgreSQL 16
- Symfony Messenger avec Redis
- Twig et CSS simple
- PHPUnit 12, PHPStan et PHP-CS-Fixer
- Docker Compose et GitHub Actions

## Architecture visée

Le projet est un monolithe modulaire organisé autour de trois fonctionnalités : `Candidate`, `Job` et `Matching`. Les interfaces sont placées uniquement aux frontières changeantes, notamment les fournisseurs d'offres, les contrôles de disponibilité et le fournisseur LLM.

Le LLM ne décidera pas seul d'un pourcentage. Il produira des observations JSON structurées, ensuite converties en impacts chiffrés par un moteur de règles configurable.

## Installation

Prérequis : Docker avec le plugin Compose et `make`.

```bash
make setup
```

L'application est ensuite accessible sur <http://localhost:8080>. PostgreSQL et Redis sont exposés respectivement sur les ports `5433` et `6380` pour les outils locaux.

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

La vérification de disponibilité et l’analyse IA des offres feront l’objet des incréments suivants.

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

## Scoring

Le score actuel est déterministe et pondère les compétences (35), l’expérience (15), le salaire (15), la localisation (10), le contrat (10), l’orientation backend (10) et le télétravail (5). Les compétences exigées par l’annonce sont comparées à celles validées dans le CV ; les exigences absentes du profil sont affichées explicitement. Les poids sont configurés dans `config/services.yaml`, pas figés dans le service métier.

Une information absente reçoit une valeur neutre de 50 et apparaît dans les informations à vérifier. Chaque import crée ou met à jour un unique résultat par couple profil/offre. Le futur score sémantique restera séparé du score déterministe et fournira des observations JSON structurées.

Les critères bloquants pourront appliquer des malus ou plafonner le score. Les données inconnues resteront neutres et seront signalées.

## Décisions

- Symfony 7.4 LTS privilégie la stabilité tout en restant compatible avec PHP 8.4.
- Redis est utilisé pour les traitements asynchrones et les futurs verrous d'idempotence.
- La file d'échec reste dans PostgreSQL afin d'être facilement inspectable.
- L'interface reste en Twig/CSS pour limiter le coût de la V1.
- Les entités Doctrine feront partie du domaine : un second modèle de persistance serait superflu ici.

## Limites actuelles

- Les PDF contenant du texte et les DOCX sont pris en charge. Un PDF constitué uniquement d'images nécessite encore un module OCR.
- L'analyse OpenAI implique l'envoi du texte du CV à un fournisseur externe et génère un coût d'API.
- Seule la première page d'une recherche HelloWork est importée, avec un maximum de dix fiches par synchronisation.
- Le scoring sémantique des offres par IA n’est pas encore implémenté ; l’orientation backend repose actuellement sur des mots-clés explicites.
- L'application n'intègre pas encore d'authentification et doit rester accessible en local.

## Roadmap

1. analyse sémantique des offres en JSON strict ;
2. contrôle de disponibilité ;
3. filtres du dashboard Twig ;
4. pagination des sources et planification quotidienne.
