# Job Matcher

Application web personnelle destinée à agréger des offres d'emploi, les comparer à un profil candidat et produire un score de compatibilité expliqué.

Le projet avance par incréments. La version actuelle contient le socle technique ainsi que le dépôt privé d'un CV, son extraction, son analyse asynchrone et la validation manuelle des informations proposées avant leur ajout au profil.

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

## Scoring prévu

Le score global combinera :

1. des règles déterministes configurables : compétences, expérience, salaire, localisation, contrat et télétravail ;
2. des classifications sémantiques structurées : orientation backend, importance réelle d'une technologie et proximité entre compétences.

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
- L'import d'offres et le moteur de scoring ne sont pas encore implémentés.
- L'application n'intègre pas encore d'authentification et doit rester accessible en local.

## Roadmap

1. modèle des offres et normalisation ;
2. import JSON et faux fournisseur ;
3. moteur de scoring déterministe ;
4. pipeline d'analyse des offres idempotent ;
5. analyse sémantique des offres en JSON strict ;
6. contrôle de disponibilité ;
7. dashboard Twig.
