# Changelog — Job Matcher

## 2026-10-28 — Récupération de déploiement

- Ajout d’un déclenchement manuel `workflow_dispatch` sur la CI ; la branche `main` repasse par QA, publication des images et déploiement protégé `production`.
- Diagnostic de l’incident 503 : démon Docker actif, conteneurs PHP/nginx non démarrés, aucune écoute sur le port applicatif 8081. Réparation de production en attente du déploiement automatisé.
- `.gitignore` couvre désormais tout fichier `*.local`.
