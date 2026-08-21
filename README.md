# Job Matcher

Application web personnelle destinée à agréger des offres d'emploi, les comparer à un profil candidat et produire un score de compatibilité expliqué.

Le projet avance par incréments. Cette première version contient uniquement le socle technique : Symfony, PostgreSQL, Redis, Messenger, Docker et les outils de qualité.

## Stack

- PHP 8.4 et Symfony 7.4 LTS
- Doctrine ORM et PostgreSQL 16
- Symfony Messenger avec Redis
- Twig et CSS simple
- PHPUnit 12, PHPStan et PHP-CS-Fixer
- Docker Compose et GitHub Actions

## Architecture visée

Le projet est un monolithe modulaire organisé autour de trois domaines : `Candidate`, `Job` et `Matching`. Les interfaces seront placées aux frontières changeantes, notamment les fournisseurs d'offres, les checkers de disponibilité et le fournisseur LLM.

Le LLM ne décidera pas seul d'un pourcentage. Il produira des observations JSON structurées, ensuite converties en impacts chiffrés par un moteur de règles configurable.

## Installation

Prérequis : Docker avec le plugin Compose et `make`.

```bash
make setup
```

L'application est ensuite accessible sur <http://localhost:8080>. PostgreSQL et Redis sont exposés respectivement sur les ports `5433` et `6380` pour les outils locaux.

## Commandes utiles

```bash
make up        # démarrer l'application et le worker
make down      # arrêter les conteneurs
make logs      # suivre les logs
make shell     # ouvrir un shell dans le conteneur PHP
make test      # lancer PHPUnit
make qa        # style, PHPStan et PHPUnit
```

Les messages asynchrones utilisent Redis. Après trois échecs avec délai exponentiel, ils sont conservés dans une file d'échec Doctrine.

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

Ce socle ne contient encore ni profil candidat, ni import d'offres, ni moteur de scoring. L'application n'intègre pas encore d'authentification et doit rester accessible en local.

## Roadmap

1. profil candidat et catalogue de compétences ;
2. modèle des offres et normalisation ;
3. import JSON et faux fournisseur ;
4. moteur de scoring déterministe ;
5. pipeline Messenger idempotent ;
6. analyse LLM JSON stricte ;
7. contrôle de disponibilité ;
8. dashboard Twig.
