# Roadmap de sécurité

Cette roadmap ordonne les travaux selon le risque pour les comptes, les CV et l'infrastructure. Elle ne contient aucune coordonnée ni aucun secret de production. Chaque point doit être livré dans un commit atomique après les tests adaptés.

## Critique

- [x] **SEC-01 — Fiabiliser l'identité des comptes**
  - [x] Interdire le rattachement Google automatique à un compte local existant.
  - [x] Exiger une session locale authentifiée et un email Google identique pour l'association explicite.
  - [x] Vérifier l'adresse email avant d'activer une nouvelle inscription locale.
  - [x] Préserver l'accès des comptes historiques via une migration explicite.
- [x] **SEC-02 — Isoler l'analyse des CV non fiables**
  - [x] Valider la signature et la structure PDF/DOCX, avec limite décompressée.
  - [x] Analyser les fichiers avec un antivirus local.
  - [x] Exécuter l'extraction sans secret, sans réseau et avec des limites CPU/mémoire/PID.
- [ ] **SEC-03 — Réduire les privilèges de déploiement**
  - [ ] Ne plus exécuter un manifeste Compose transféré directement depuis le dépôt.
  - [ ] Restreindre la clé SSH à une commande de déploiement fixe.
  - [ ] N'autoriser que les images immuables attendues et un SHA validé.

## Haute

- [ ] **SEC-04 — Protéger le dépôt et la chaîne logicielle**
  - [ ] Protéger `main`, exiger la CI et la revue des fichiers sensibles.
  - [ ] Activer la détection de secrets et la protection des pushs.
  - [ ] Auditer Composer et les images, puis produire SBOM et attestations.
- [ ] **SEC-05 — Durcir Cloudflare et l'origine**
  - [ ] Restaurer et valider l'IP réelle du visiteur de bout en bout.
  - [ ] Empêcher le contournement direct de Cloudflare.
  - [ ] Conserver TLS `Full (strict)` et automatiser son contrôle.
- [ ] **SEC-06 — Durcir HTTP, sessions et limitations**
  - [ ] Déployer une CSP d'abord en observation, puis en blocage.
  - [ ] Ajouter HSTS, `nosniff`, `Referrer-Policy`, `Permissions-Policy` et `frame-ancestors`.
  - [ ] Forcer les cookies sécurisés en production et limiter les routes coûteuses ou publiques.

## Protection et détection

- [ ] **SEC-07 — Sauvegarder et restaurer les données sensibles**
  - [ ] Chiffrer et externaliser les sauvegardes PostgreSQL et CV.
  - [ ] Définir une rétention et tester une restauration complète.
- [ ] **SEC-08 — Journaliser et alerter sans exposer les données**
  - [ ] Journaliser les événements d'authentification et actions sensibles.
  - [ ] Exclure CV, jetons, secrets et prompts complets des journaux.
  - [ ] Alerter sur les échecs répétés, files bloquées et indisponibilités.
