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

- **Backend** : Symfony (PHP)
- **Base de données** : MySQL / MariaDB (via Doctrine ORM)
- **Frontend** : _à préciser_
- **Autres** : _à compléter au fur et à mesure_

## 📐 Documentation & conception

Toute la documentation de conception se trouve dans le dossier [`/docs`](./docs) :

| Fichier | Contenu |
|---|---|
| `docs/user-stories.md` | Epics et user stories du projet |
| `docs/mcd.png` / `docs/mld.png` | Modèle Conceptuel et Logique de Données |
| `docs/diagrams/er-diagram.md` | Diagramme entité-relation (Mermaid) |
| `docs/diagrams/flowchart-generation.md` | Flux de génération d'un plan d'entraînement |
| `docs/diagrams/sequence-generation.md` | Diagramme de séquence — génération initiale |
| `docs/diagrams/sequence-adaptation.md` | Diagramme de séquence — boucle d'adaptation |

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
