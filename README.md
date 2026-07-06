# mon_allure
Site de création de plan d'entraînement pour la course à pied, suivant plusieurs objectifs découverte et bien être, progression et performance. 
# Découpage Epics → Issues GitHub — Mon Allure

Ce document liste chaque Epic comme une **issue parente** (à créer en premier, label `epic`), suivie de ses User Stories en **sous-issues** (à créer et lier à l'Epic via la fonctionnalité "Sub-issues" de GitHub, ou en cochant les cases dans la description de l'Epic).

Pour chaque US, un découpage en tâches techniques est proposé — tu peux les garder en checklist dans l'issue, ou les éclater en issues encore plus petites si tu préfères un suivi plus fin.

---

## EPIC 1 — Authentification & compte
**Label epic** : `epic:auth` · **Labels génériques** : `backend`, `security`

### Issue — US 1.1 : Création de compte
> En tant qu'utilisateur, je veux pouvoir créer un compte afin d'accéder à l'application.

**Critères d'acceptation :**
- [ ] Un utilisateur peut s'inscrire avec email + mot de passe
- [ ] L'email est unique, le mot de passe est hashé
- [ ] Un pseudo unique est demandé
- [ ] Validation des champs (format email, longueur mdp min.)

**Tâches techniques :**
- [ ] Entité `User` + migration Doctrine
- [ ] `RegistrationController` + endpoint `POST /api/register`
- [ ] Hash du mot de passe (`UserPasswordHasher`)
- [ ] Tests unitaires validation

**Labels :** `feature`, `backend`, `priority:high`

---

### Issue — US 1.2 : Connexion / déconnexion
> En tant qu'utilisateur, je veux pouvoir me connecter et me déconnecter afin de sécuriser mes données personnelles.

**Critères d'acceptation :**
- [ ] Connexion via email + mot de passe
- [ ] Génération d'un token (JWT ou session selon choix technique)
- [ ] Déconnexion invalide le token/session
- [ ] Message d'erreur clair si identifiants invalides

**Tâches techniques :**
- [ ] Configuration `security.yaml` (firewall, provider)
- [ ] Endpoint `POST /api/login`
- [ ] Endpoint `POST /api/logout`
- [ ] Middleware/guard d'authentification pour les routes protégées

**Labels :** `feature`, `backend`, `security`, `priority:high`

---

## EPIC 2 — Profil & calibrage sportif
**Label epic** : `epic:profile` · **Labels génériques** : `backend`, `frontend`

### Issue — US 2.1 : Compléter le profil physiologique
> En tant que nouvel utilisateur, je veux compléter un profil physiologique (âge, VMA, FCM, FCR) afin que l'application adapte mes entraînements.

**Critères d'acceptation :**
- [ ] Formulaire de saisie (âge, VMA, FCM, FCR)
- [ ] Le profil est lié 1-1 à l'utilisateur
- [ ] Validation des valeurs (bornes réalistes)

**Tâches techniques :**
- [ ] Entité `Profile` + migration Doctrine
- [ ] `ProfileController` + endpoint `POST /api/profile`
- [ ] `ProfileRepository`
- [ ] Formulaire frontend de calibrage initial

**Labels :** `feature`, `backend`, `frontend`, `priority:high`

---

### Issue — US 2.2 : Mettre à jour les données physiologiques
> En tant qu'utilisateur, je veux pouvoir mettre à jour mes données physiologiques afin que le plan reste cohérent avec mon niveau réel.

**Critères d'acceptation :**
- [ ] Endpoint de mise à jour du profil existant
- [ ] `updated_at` rafraîchi automatiquement
- [ ] Historique des modifications non requis (sauf besoin futur)

**Tâches techniques :**
- [ ] Endpoint `PUT /api/profile`
- [ ] Validation des nouvelles valeurs

**Labels :** `feature`, `backend`, `priority:medium`

---

### Issue — US 2.3 : Définir un objectif de course
> En tant qu'utilisateur, je veux définir un objectif de course (distance et/ou durée avec unité) afin de préparer un plan d'entraînement adapté.

**Critères d'acceptation :**
- [ ] Choix du type d'objectif (distance ou temps)
- [ ] Choix de l'unité (km, m, seconde, minute)
- [ ] Choix du type de terrain (route, chemin, trail)
- [ ] Dénivelé positif visé optionnel

**Tâches techniques :**
- [ ] DTO `TrainingPlanDTO` (partiel, côté saisie objectif)
- [ ] Formulaire frontend de définition d'objectif

**Labels :** `feature`, `backend`, `frontend`, `priority:high`

---

## EPIC 3 — Génération du plan d'entraînement
**Label epic** : `epic:plan-generation` · **Labels génériques** : `backend`, `algorithm`

### Issue — US 3.1 : Consulter un plan d'entraînement personnalisé
> En tant qu'utilisateur, je veux consulter un plan d'entraînement personnalisé généré dynamiquement afin de savoir quoi faire chaque semaine.

**Critères d'acceptation :**
- [ ] Le plan est généré à partir du profil, de l'objectif et des paramètres algorithme
- [ ] Affichage des séances par semaine
- [ ] Chaque séance a un titre, des instructions, une date

**Tâches techniques :**
- [ ] Entités `TrainingPlan`, `Session` + migrations
- [ ] `TrainingPlanGeneratorService`, `FeasibilityService`, `PaceCalculatorService`, `SessionGeneratorService`
- [ ] Endpoint `POST /api/training-plans`
- [ ] Endpoint `GET /api/training-plans/{id}`

**Labels :** `feature`, `backend`, `algorithm`, `priority:high`

---

### Issue — US 3.2 : Adaptation automatique du plan
> En tant qu'utilisateur, je veux que mon plan s'adapte automatiquement à mes performances réelles afin de progresser efficacement.

**Critères d'acceptation :**
- [ ] Chaque performance enregistrée déclenche une évaluation
- [ ] Le `progress_score` du plan est mis à jour

**Tâches techniques :**
- [ ] Entité `Performance` + migration
- [ ] `AdaptationService`
- [ ] Endpoint `POST /api/performances`

**Labels :** `feature`, `backend`, `algorithm`, `priority:high`

---

### Issue — US 3.3 : Recalcul du plan selon séances ratées/réussies
> En tant qu'utilisateur, je veux que mon plan soit recalculé si je rate ou réussis plusieurs séances afin de maintenir un niveau de progression optimal.

**Critères d'acceptation :**
- [ ] Taux de réussite calculé sur les séances récentes
- [ ] Charge augmentée si réussite >= seuil (`success_validation_rate`)
- [ ] Charge réduite/stabilisée sinon
- [ ] Les séances futures sont régénérées en conséquence

**Tâches techniques :**
- [ ] `AdaptationService::adapt()` (logique complète)
- [ ] `SessionGeneratorService::regenerateUpcoming()`

**Labels :** `feature`, `backend`, `algorithm`, `priority:medium`

---

### Issue — US 3.4 : Export du plan en PDF
> En tant qu'utilisateur, je veux exporter mon plan au format PDF afin de pouvoir le consulter hors ligne.

**Critères d'acceptation :**
- [ ] Bouton d'export sur la page du plan
- [ ] PDF lisible avec toutes les séances

**Tâches techniques :**
- [ ] Librairie de génération PDF (ex: Dompdf, wkhtmltopdf)
- [ ] Endpoint `GET /api/training-plans/{id}/export`
- [ ] Template PDF

**Labels :** `feature`, `backend`, `priority:low`

---

## EPIC 4 — Dashboard utilisateur
**Label epic** : `epic:dashboard` · **Labels génériques** : `frontend`, `backend`

### Issue — US 4.1 : État d'avancement du plan
> En tant qu'utilisateur, je veux voir l'état d'avancement de mon plan afin de savoir où j'en suis dans ma préparation.

**Critères d'acceptation :**
- [ ] Affichage semaine actuelle / total
- [ ] Barre de progression basée sur `current_week` / `duration_weeks`

**Labels :** `feature`, `frontend`, `priority:medium`

---

### Issue — US 4.2 : Temps restant avant l'objectif
> En tant qu'utilisateur, je veux visualiser le temps restant avant mon objectif afin de me projeter dans ma préparation.

**Critères d'acceptation :**
- [ ] Calcul du nombre de jours/semaines restants jusqu'à `end_date`

**Labels :** `feature`, `frontend`, `priority:low`

---

### Issue — US 4.3 : État de forme actuel
> En tant qu'utilisateur, je veux connaître mon état de forme actuel basé sur mes performances récentes afin d'adapter mon effort.

**Critères d'acceptation :**
- [ ] Indicateur basé sur `progress_score` et les dernières performances

**Tâches techniques :**
- [ ] Endpoint `GET /api/dashboard/form-status`

**Labels :** `feature`, `backend`, `frontend`, `priority:medium`

---

### Issue — US 4.4 : Consultation météo
> En tant qu'utilisateur, je veux consulter la météo afin d'adapter ma tenue et mes conditions d'entraînement.

**Critères d'acceptation :**
- [ ] Intégration d'une API météo externe
- [ ] Affichage sur le dashboard

**Tâches techniques :**
- [ ] Choix d'une API météo (ex: OpenWeatherMap)
- [ ] Service d'appel API + cache

**Labels :** `feature`, `frontend`, `integration`, `priority:low`

---

## EPIC 5 — Suivi & statistiques
**Label epic** : `epic:stats` · **Labels génériques** : `backend`, `frontend`

### Issue — US 5.1 : Historique des séances
> En tant qu'utilisateur, je veux consulter l'historique de mes séances afin de suivre ma progression.

**Critères d'acceptation :**
- [ ] Liste des séances passées avec statut (générée, validée, manquée)

**Labels :** `feature`, `backend`, `frontend`, `priority:medium`

---

### Issue — US 5.2 : Visualisation des performances passées
> En tant qu'utilisateur, je veux visualiser mes performances passées afin d'analyser mon évolution (distance, temps, dénivelé).

**Critères d'acceptation :**
- [ ] Graphiques d'évolution (distance, temps, dénivelé) dans le temps

**Labels :** `feature`, `frontend`, `priority:medium`

---

### Issue — US 5.3 : Répartition par zones d'intensité
> En tant qu'utilisateur, je veux connaître la répartition de mes entraînements par zones d'intensité afin d'évaluer la qualité de mon plan.

**Critères d'acceptation :**
- [ ] Graphique de répartition basé sur `SESSION_INTENSITY_ZONE`

**Labels :** `feature`, `backend`, `frontend`, `priority:low`

---

## EPIC 6 — Administration & algorithme
**Label epic** : `epic:admin` · **Labels génériques** : `backend`, `admin`

### Issue — US 6.1 : Gestion des comptes utilisateurs
> En tant qu'administrateur, je veux pouvoir gérer les comptes utilisateurs afin de supprimer ou désactiver les comptes inactifs.

**Critères d'acceptation :**
- [ ] Liste des utilisateurs (admin uniquement)
- [ ] Activation/désactivation d'un compte
- [ ] Suppression d'un compte

**Tâches techniques :**
- [ ] Rôle `ROLE_ADMIN` + restriction d'accès
- [ ] Endpoints `GET /api/admin/users`, `PATCH /api/admin/users/{id}`

**Labels :** `feature`, `backend`, `admin`, `priority:medium`

---

### Issue — US 6.2 : Modifier les paramètres de l'algorithme
> En tant qu'administrateur, je veux pouvoir modifier les paramètres de l'algorithme afin d'ajuster les plans d'entraînement sans modifier le code.

**Critères d'acceptation :**
- [ ] Interface d'édition de `ALGORITHM_PARAMETER`
- [ ] Changements pris en compte immédiatement pour les nouveaux plans

**Tâches techniques :**
- [ ] Entité `AlgorithmParameter` + migration
- [ ] `AlgorithmParameterRepository`
- [ ] Endpoints `GET/PUT /api/admin/algorithm-parameters`

**Labels :** `feature`, `backend`, `admin`, `algorithm`, `priority:high`

---

### Issue — US 6.3 : Superviser le comportement des plans générés
> En tant qu'administrateur, je veux pouvoir superviser le comportement des plans générés afin de détecter d'éventuelles incohérences dans l'algorithme.

**Critères d'acceptation :**
- [ ] Vue admin listant les plans avec leur `feasibility_indicator` et `progress_score`
- [ ] Filtres (par statut de faisabilité, par utilisateur)

**Labels :** `feature`, `backend`, `admin`, `priority:low`

---

### Issue — US 6.4 : Faire évoluer l'interface et les fonctionnalités
> En tant qu'administrateur, je veux pouvoir faire évoluer l'interface et les fonctionnalités afin d'améliorer l'expérience utilisateur.

**Critères d'acceptation :**
- [ ] Backlog évolutif documenté (issues futures)

**Labels :** `enhancement`, `priority:low`

---

## Suggestion de labels GitHub à créer

| Label | Couleur suggérée | Usage |
|---|---|---|
| `epic` | violet | Issue parente d'un Epic |
| `feature` | vert | Nouvelle fonctionnalité |
| `backend` | bleu | Travail côté API/Symfony |
| `frontend` | jaune | Travail côté interface |
| `algorithm` | orange | Logique de génération/adaptation |
| `admin` | gris | Fonctionnalités d'administration |
| `security` | rouge | Authentification, sécurité |
| `enhancement` | bleu clair | Amélioration continue |
| `priority:high` / `priority:medium` / `priority:low` | rouge/orange/vert clair | Priorisation |

## Comment les créer rapidement sur GitHub

1. Crée d'abord les 6 issues **Epic** (une par grande section ci-dessus), label `epic`
2. Pour chaque US, crée une issue avec le titre, la description, les critères d'acceptation et les tâches techniques copiés depuis ce document
3. Lie chaque issue US à son Epic via la fonctionnalité **Sub-issues** de GitHub (bouton "Add sub-issue" dans l'Epic), ou via une checklist `- [ ] #<numéro>` dans la description de l'Epic
4. Ajoute les labels correspondants à chaque issue
