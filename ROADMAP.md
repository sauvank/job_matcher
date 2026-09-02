# Roadmap unifiée — Job Matcher

Ce document centralise l’ensemble des axes d'évolution de **Job Matcher**, couvrant les fonctionnalités produit (recherche, matching, candidatures, IA) ainsi que le durcissement technique et la sécurité de l'infrastructure.

---

## 🧭 Vue d’ensemble des statuts

| Thème | Statut global | Prochaine étape prioritaire |
|---|---|---|
| **1. Collecte & Connecteurs** | 🟢 Opérationnel (6 fournisseurs) | Connecteur Malt & flux RSS tech |
| **2. Profil & Préférences** | 🟢 Opérationnel | Préférence stricte de télétravail (Full Remote) |
| **3. Moteur de Matching & IA** | 🟢 Opérationnel (Déterministe + LLM) | Blacklist d'entreprises & mots-clés repoussoirs |
| **4. Suivi des candidatures (ATS)** | 🟡 Planifié | Tableau Kanban de suivi & historique de candidature |
| **5. Assistant de postulation IA** | 🟡 Planifié | Générateur de lettre / pitch sur-mesure |
| **6. Alertes & Notifications** | 🟢 Opérationnel (Emails quotidiens) | Notifications instantanées Telegram / Webhook |
| **7. Sécurité & Infrastructure** | 🟡 En cours (Sandbox & Auth OK) | Headers HTTP (CSP, HSTS) & Sauvegardes chiffrées |

---

## 🎯 1. Fonctionnalités Produit

### 1.1 Profil candidat & Filtres de recherche
- [x] Extraction automatique des compétences, expérience et intitulé de poste depuis le CV (PDF / DOCX).
- [x] Configuration assistée de recherches ciblées intelligentes multi-fournisseurs.
- [x] Gestion des compétences principales et complémentaires avec auto-complétion et niveau de maîtrise.
- [x] Prétentions de salaire annuel brut minimum et de TJM minimum (Freelance).
- [x] Filtrage strict des annonces et alertes selon les types de contrat choisis (CDI, Freelance, CDD, Alternance, Stage).
- [ ] **Filtrage strict par politique de télétravail** : masquer les annonces ne correspondant pas au mode de travail souhaité (ex: 100% Full Remote uniquement).
- [ ] **Blacklist d'entreprises & mots-clés repoussoirs** : exclure les ESN non désirées ou les annonces contenant certaines technologies (ex: *WordPress*, *Prestashop*, *Legacy*).
- [ ] **Gestion Multi-profils / Multi-CVs** : pouvoir basculer entre plusieurs CVs ciblés (ex: *Lead Tech CDI* vs *Expert Backend Freelance*).

### 1.2 Moteur de Matching & Analyse sémantique
- [x] Moteur de scoring déterministe pondéré (stack technique, expérience, salaire/TJM, contrat, localisation, télétravail, affinité backend).
- [x] Analyse sémantique LLM (OpenAI / Gemini) avec extraction des points forts, points de vigilance et arguments.
- [x] Exclusion automatique des offres expirées ou obsolètes.
- [ ] **Ajustement dynamique des pondérations par profil** : permettre au candidat de pondérer lui-même l'importance du salaire vs stack vs télétravail.
- [ ] **Détection des doublons d'annonces multi-plateformes** : regrouper une même offre diffusée simultanément sur Apec, HelloWork et WTTJ.

### 1.3 Suivi des candidatures (Mini-ATS & Kanban)
- [ ] **Tableau de bord de suivi (Kanban)** :
  - Colonnes : *À postuler* ➔ *Candidature envoyée* ➔ *Entretien RH / Tech* ➔ *Offre reçue* ➔ *Refusé / Archivé*.
- [ ] **Notes personnelles et contacts** : enregistrer les coordonnées du recruteur, lien de visioconférence et notes de compte-rendu.
- [ ] **Rappels automatiques de relance** : suggestion de relance à J+7 après l'envoi d'une candidature sans réponse.

### 1.4 Assistant IA pour postuler
- [ ] **Générateur de pitch / mail d'accroche** : rédaction d'un message concis mettant en valeur les compétences communes identifiées par le matching.
- [ ] **Générateur de lettre de motivation sur-mesure** : génération d'un document personnalisé adapté à l'offre et au CV actif.
- [ ] **Préparateur d'entretien technique** : génération de questions pièges ou techniques basées sur les exigences de l'annonce et les écarts du CV.

### 1.5 Connecteurs & Sources d'offres
- [x] **HelloWork** (synchronisation automatique).
- [x] **Apec** (synchronisation API publique).
- [x] **France Travail** (synchronisation API ouverte).
- [x] **Welcome to the Jungle** (synchronisation API Algolia publique).
- [x] **Free-Work / Freelance-Info** (synchronisation API Platform publique).
- [x] **Indeed** (scraping avec gestion explicite de blocage).
- [ ] **Malt** (missions freelance ouvertes & flux RSS).
- [ ] **WeLoveDevs / LesJeudis** (plateformes tech dédiées).
- [ ] **Support de flux RSS / Atom personnalisés**.

### 1.6 Alertes & Notifications
- [x] Envoi d'un email récapitulatif quotidien avec les meilleures opportunités compatibles.
- [ ] **Notifications instantanées multicanales** : bot Telegram, webhook Discord ou Slack dès qu'une offre avec un score ≥ 85% est détectée.
- [ ] **Synthèse hebdomadaire du marché** : statistiques sur les nouvelles offres détectées et tendances des TJM / salaires dans la région.

---

## 🔒 2. Sécurité & Infrastructure

### 2.1 Authentification & Identité
- [x] **SEC-01 — Fiabiliser l'identité des comptes** :
  - [x] Interdire le rattachement Google automatique à un compte local existant.
  - [x] Exiger une session locale authentifiée et un email Google identique pour l'association explicite.
  - [x] Vérifier l'adresse email avant d'activer une nouvelle inscription locale.
  - [x] Préserver l'accès des comptes historiques via migration sécurisée.

### 2.2 Analyse sécurisée des CV & Sandbox
- [x] **SEC-02 — Isoler l'analyse des CV non fiables** :
  - [x] Valider la signature et la structure PDF/DOCX avec limite de taille décompressée.
  - [x] Scanner systématiquement les fichiers avec un antivirus local (ClamAV).
  - [x] Exécuter l'extraction de texte dans un conteneur dédié, sans secret, sans accès réseau et avec quotas CPU/RAM/PID.

### 2.3 Déploiement & Chaîne d'intégration
- [ ] **SEC-03 — Réduire les privilèges de déploiement** :
  - [ ] Ne plus exécuter un manifeste Compose transféré directement depuis le dépôt.
  - [ ] Restreindre la clé SSH à une commande de déploiement fixe.
  - [ ] N'autoriser que les images immuables attendues et un SHA validé.
- [ ] **SEC-04 — Protéger le dépôt et la chaîne logicielle** :
  - [ ] Protéger la branche `main`, exiger la CI et la revue des fichiers sensibles.
  - [ ] Activer la détection automatique de secrets et la protection des commits.
  - [ ] Auditer les dépendances Composer et produire un SBOM.

### 2.4 Durcissement Réseau & Application
- [ ] **SEC-05 — Durcir Cloudflare et l'origine** :
  - [ ] Restaurer et valider l'IP réelle du visiteur de bout en bout.
  - [ ] Empêcher le contournement direct de Cloudflare.
  - [ ] Conserver TLS `Full (strict)` et automatiser son contrôle.
- [ ] **SEC-06 — Durcir HTTP, sessions et limitations** :
  - [ ] Déployer une CSP (*Content Security Policy*) d'abord en observation, puis en blocage.
  - [ ] Ajouter les en-têtes de sécurité HSTS, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` et `frame-ancestors`.
  - [ ] Forcer les cookies sécurisés en production et appliquer un rate limiting sur les routes d'authentification et d'import.

### 2.5 Résilience, Sauvegardes & Observabilité
- [ ] **SEC-07 — Sauvegarder et restaurer les données sensibles** :
  - [ ] Chiffrer et externaliser les sauvegardes PostgreSQL et des CV.
  - [ ] Définir une politique de rétention et tester régulièrement une restauration complète.
- [ ] **SEC-08 — Journaliser et alerter sans exposer les données** :
  - [ ] Journaliser les événements d'authentification et actions sensibles.
  - [ ] Exclure les CV, jetons, secrets et prompts complets des journaux.
  - [ ] Alerter sur les échecs répétés, files de messages bloquées et indisponibilités de services.
