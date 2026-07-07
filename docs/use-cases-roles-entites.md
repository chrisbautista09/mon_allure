# Uses Cases — Mon Allure — Rôles et Entités 

## 1. RÔLES

- Visiteur
- Utilisateur
- Administrateur

## 2. ENTITÉS

- User
- Profile
- TrainingPlan
- Session
- IntensityZone
- Performance
- Comment
- AlgorithmParameter

---

## EPIC 1 — PROFIL

### UC-101 — Créer un compte
Acteur : Visiteur
Entités : User (C), Profile (C)

### UC-102 — Se connecter
Acteur : Utilisateur
Entités : User (R)

### UC-103 — Modifier son profil
Acteur : Utilisateur
Entités : Profile (U)

---

## EPIC 2 — OBJECTIF & PARAMÉTRAGE

### UC-201 — Définir un niveau (pôle)
Acteur : Utilisateur
Entités : TrainingPlan (C)

> Le pôle (`pole_type`) est choisi **au moment de la création** du plan, pas modifiable après coup sur un plan existant.

### UC-202 — Définir un objectif de course
Acteur : Utilisateur
Entités : TrainingPlan (C/U)

- distance / temps / D+
- terrain
- unité
- faisabilité calculée automatiquement

---

## EPIC 3 — GÉNÉRATION & CONSULTATION

### UC-301 — Consulter son plan
Acteur : Utilisateur
Entités : TrainingPlan (R), Session (R)

### UC-302 — Consulter une séance
Acteur : Utilisateur
Entités : Session (R), IntensityZone (R)

> La consultation d'une séance affiche aussi les zones d'intensité ciblées (endurance, seuil, VMA...).

### UC-303 — Update plan (Recalculer son plan d'entraînement)
Acteur : Utilisateur
Entités : TrainingPlan (U), Performance (R), Session (U)

### UC-304 — Consulter les zones d'intensité
Acteur : Utilisateur
Entités : IntensityZone (R)

---

## EPIC 4 — SUIVI & PERFORMANCE

### UC-401 — Enregistrer une performance
Acteur : Utilisateur
Entités : Performance (C)

- peut déclencher adaptation du plan

### UC-402 — Consulter statistiques
Acteur : Utilisateur
Entités : Performance (R), Profile (R)

### UC-403 — Exporter son plan d'entraînement
Acteur : Utilisateur
Entités : TrainingPlan (R), Session (R)

> Export uniquement du plan et de ses séances — ne concerne ni Performance ni Profile.

---

## EPIC 5 — OPTIMISATION AUTOMATIQUE DES PLANS

### UC-501 — Adapter automatiquement le plan
Acteur : Système
Entités : TrainingPlan (U), Performance (R), Session (U)

- déclenché après UC-401

### UC-502 — Appliquer règles algorithmiques
Acteur : Système
Entités : AlgorithmParameter (R)

---

## EPIC 6 — COMMENTAIRES

### UC-601 — Ajouter un commentaire
Acteur : Utilisateur
Entités : Comment (C)

### UC-602 — Modérer commentaire
Acteur : Administrateur
Entités : Comment (U)

---

## EPIC 7 — ADMINISTRATION

### UC-701 — Gérer utilisateurs
Acteur : Administrateur
Entités : User (R/U/D)

### UC-702 — Modifier paramètres algorithmiques
Acteur : Administrateur
Entités : AlgorithmParameter (U)

### UC-703 — Supervision des plans générés
Acteur : Administrateur
Entités : TrainingPlan (R), Session (R)

### UC-704 — Supprimer commentaire
Acteur : Administrateur
Entités : Comment (D)

---

## 3. MATRICE CRUD COMPLÈTE

| Acteur | UC | Entités |
| --- | --- | --- |
| Visiteur | UC-101 | User (C), Profile (C) |
| Utilisateur | UC-102 | User (R) |
| Utilisateur | UC-103 | Profile (U) |
| Utilisateur | UC-201 | TrainingPlan (C) |
| Utilisateur | UC-202 | TrainingPlan (C/U) |
| Utilisateur | UC-301 | TrainingPlan (R), Session (R) |
| Utilisateur | UC-302 | Session (R), IntensityZone (R) |
| Utilisateur | UC-303 | TrainingPlan (U), Performance (R), Session (U) |
| Utilisateur | UC-304 | IntensityZone (R) |
| Utilisateur | UC-401 | Performance (C) |
| Utilisateur | UC-402 | Performance (R), Profile (R) |
| Utilisateur | UC-403 | TrainingPlan (R), Session (R) |
| Système | UC-501 | TrainingPlan (U), Performance (R), Session (U) |
| Système | UC-502 | AlgorithmParameter (R) |
| Utilisateur | UC-601 | Comment (C) |
| Administrateur | UC-602 | Comment (U) |
| Administrateur | UC-701 | User (R/U/D) |
| Administrateur | UC-702 | AlgorithmParameter (U) |
| Administrateur | UC-703 | TrainingPlan (R), Session (R) |
| Administrateur | UC-704 | Comment (D) |

---

