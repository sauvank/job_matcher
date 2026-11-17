# Changelog — Job Matcher

## 2026-11-17 — Analyses en file annulées à tort

- La récupération des analyses bloquées (ouverture de `/jobs`, `--recover`) marquait `FAILED` toute analyse `QUEUED` depuis plus de 10 min ; lors d'une relance en lot, la fin de la file était donc annulée (26 sur 66 en prod).
- `RUNNING` reste bloqué après 10 min ; `QUEUED` attend désormais `JobMatch::QUEUED_ANALYSIS_TIMEOUT_MINUTES` (120 min).

## 2026-11-17 — Alerte crédit IA épuisé

- Constat prod : crédit OpenAI vide depuis le 9 novembre. OpenAI renvoie `429` avec `type: insufficient_quota` et `code: credit_balance_exhausted` ; l'ancien test sur `code === 'insufficient_quota'` le classait en `openai_rate_limited` et réessayait inutilement.
- Les analyseurs OpenAI (offres et CV) reconnaissent les deux formes : erreur `openai_quota_exceeded`, non réessayée.
- `AiCreditAlertNotifier` envoie un e-mail aux comptes `ROLE_ADMIN` lorsque le crédit OpenAI ou Gemini est épuisé, au plus une fois par 24 h et par fournisseur (verrou dans `cache.app`, Redis). Un échec SMTP n'interrompt pas l'analyse.

## 2026-11-17 — Offres HelloWork retirées

- HelloWork laisse les annonces retirées en ligne avec un HTTP 200 et le bandeau « Cette offre d'emploi … n'est plus disponible », sans `JobPosting`. La vérification ne les détectait pas : le parseur échouait, l'annonce restait `UNKNOWN` et donc active (ex. offre prod 907, HelloWork 85249087).
- `HelloWorkJobPostingParser::isNoLongerAvailable()` reconnaît ce bandeau et `HelloWorkJobProvider::checkAvailability()` renvoie alors `EXPIRED`. Une page 200 illisible reste `UNKNOWN`.

## 2026-10-28 — Récupération de déploiement

- Ajout d’un déclenchement manuel `workflow_dispatch` sur la CI ; la branche `main` repasse par QA, publication des images et déploiement protégé `production`.
- Diagnostic : démon Docker actif, conteneurs PHP/nginx non démarrés, aucune écoute sur le port applicatif 8081. Rétablissement par le déploiement automatisé du commit `dba7b46` (run GitHub `40758665089`) ; `/health`, `/` et `/connexion` répondent en HTTP 200.
- Le worker de production exécute PHP 8.4.27, version corrigeant les deux CVE critiques publiées le 21 octobre 2026 ; elles n’étaient pas la cause du 503.
- `.gitignore` couvre désormais tout fichier `*.local`.
