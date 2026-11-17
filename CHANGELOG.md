# Changelog — Job Matcher

## 2026-11-17 — Offres HelloWork retirées

- HelloWork laisse les annonces retirées en ligne avec un HTTP 200 et le bandeau « Cette offre d'emploi … n'est plus disponible », sans `JobPosting`. La vérification ne les détectait pas : le parseur échouait, l'annonce restait `UNKNOWN` et donc active (ex. offre prod 907, HelloWork 85249087).
- `HelloWorkJobPostingParser::isNoLongerAvailable()` reconnaît ce bandeau et `HelloWorkJobProvider::checkAvailability()` renvoie alors `EXPIRED`. Une page 200 illisible reste `UNKNOWN`.

## 2026-10-28 — Récupération de déploiement

- Ajout d’un déclenchement manuel `workflow_dispatch` sur la CI ; la branche `main` repasse par QA, publication des images et déploiement protégé `production`.
- Diagnostic : démon Docker actif, conteneurs PHP/nginx non démarrés, aucune écoute sur le port applicatif 8081. Rétablissement par le déploiement automatisé du commit `dba7b46` (run GitHub `40758665089`) ; `/health`, `/` et `/connexion` répondent en HTTP 200.
- Le worker de production exécute PHP 8.4.27, version corrigeant les deux CVE critiques publiées le 21 octobre 2026 ; elles n’étaient pas la cause du 503.
- `.gitignore` couvre désormais tout fichier `*.local`.
