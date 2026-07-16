# 🏃 Mon Allure

**Mon Allure** est une application web qui génère et adapte automatiquement des plans d'entraînement de course à pied personnalisés, en fonction du profil physiologique du coureur, de son objectif de course et de ses performances réelles.

## 🎯 Objectif du projet

Permettre à un coureur, débutant ou confirmé, de définir un objectif de course (distance et/ou temps) et de recevoir un plan d'entraînement sur mesure qui s'ajuste automatiquement semaine après semaine selon ses résultats réels — sans avoir besoin d'un coach humain.

## ✨ Fonctionnalités principales

- **Authentification & compte** — création de compte, connexion sécurisée
- **Profil physiologique** — VMA, FCM, FCR, âge, mis à jour au fil du temps
- **Génération de plan d'entraînement** — plan dynamique et personnalisé selon l'objectif et le niveau
- **Adaptation automatique** — recalcul du plan selon les séances réussies ou manquées
- **Export PDF** — plan consultable hors ligne
- **Dashboard** — avancement du plan, temps restant, état de forme, météo
- **Suivi & statistiques** — historique des séances, performances passées, répartition par zones d'intensité
- **Administration** — gestion des comptes, ajustement des paramètres de l'algorithme, supervision des plans générés

## 🏗️ Stack technique

- **Backend** : **Symfony (PHP)** : Choisi pour sa robustesse, sa sécurité native et sa puissance dans la gestion des bases de données relationnelles. Symfony servira exclusivement d'API pour piloter de maniére ultra-fiable la logique métier, les algorithmes de calcul (VMA/VO2 max) et la génération dynamique des plans d'entraînement.
- **Base de données** : **MySQL** (via Doctrine ORM)
- **Frontend** : **React.js** Utilisé pour concevoir une interface utilisateur de type Single Page Application (SPA). Ce framework garantit une navigation instantanée, fluide et sans rechargement de page, ce qui est idéal pour l'interactivité. Ce choix technique "découplé" ouvre également la porte à une évolution future trés simple vers une application mobile native (via React Native).
- **Design & UI (Tailwind CSS)** : Ce framework CSS utilitaire permettra de concevoir une interface épurée, moderne et respectant scrupuleusement les contraintes d'affichage Mobile-First, indispensables pour un coureur consultant ses séances sur le terrain.

## 📐 Documentation & conception

Toute la documentation de conception se trouve dans le dossier [`/docs`](./docs) :

| Fichier | Contenu |
|---|---|
| `docs/user-stories.md` | Epics et user stories du projet |
| `docs/use-cases-roles-entites.md` | Use case et entités rôles |
| `docs/mcd-mon-allure.png` / `docs/mld-mon-allure.png` | Modèle Conceptuel et Logique de Données |
| `diagramme séquence flux génération plan.png` | Flux de génération d'un plan d'entraînement |
| `diagram de flux complet Mon Allure.png` | Diagramme de flux complet Mon Allure |
| `diagramme boucle d'adaptation .png` | Diagramme de séquence — boucle continue d'adaptation |

## 🗺️ Roadmap (Epics)

- [ ] **Epic 1** — Authentification & compte
- [ ] **Epic 2** — Profil & calibrage sportif
- [ ] **Epic 3** — Génération du plan d'entraînement
- [ ] **Epic 4** — Dashboard utilisateur
- [ ] **Epic 5** — Suivi & statistiques
- [ ] **Epic 6** — Administration & algorithme

Le détail de chaque Epic, découpé en User Stories et tickets techniques, est disponible dans les [Issues GitHub](../../issues) du repository.

## 🚀 Installation

_À compléter une fois le squelette du projet initialisé._

```bash
# Cloner le projet
git clone <url-du-repo>
cd mon-allure

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env .env.local
# éditer .env.local avec vos identifiants de BDD

# Créer la base de données et lancer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Lancer le serveur
symfony server:start
```

## 📌 Statut du projet

🚧 En cours de développement — phase de mise en place du squelette technique.

## 📄 Licence

_À définir._
