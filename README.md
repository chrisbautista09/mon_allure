# 🏃 Mon Allure

**Mon Allure** est une application web en cours de développement, conçue pour générer des plans d'entraînement de course à pied personnalisés à partir du profil physiologique du coureur, de son objectif et des paramètres de l'algorithme. Le plan pourra ensuite adapter ses séances futures à la fin de chaque bloc complet de trois semaines, selon les performances réellement enregistrées.

## Objectif du projet

Permettre à un coureur, débutant ou confirmé, de définir un objectif de durée, de distance ou de course chronométrée et de recevoir un plan d'entraînement sur mesure. À la fin de chaque bloc complet de trois semaines, les résultats enregistrés pourront entraîner l'ajustement des séances futures encore planifiées, sans modifier les séances déjà terminées.

## Fonctionnalités prévues

- **Authentification & compte** — création de compte, connexion sécurisée
- **Profil physiologique** — VMA, FCM, FCR, âge, mis à jour au fil du temps
- **Génération de plan d'entraînement** — plan dynamique et personnalisé selon l'objectif et le niveau
- **Adaptation automatique** — ajustement des séances futures à la fin d'un bloc complet de trois semaines, selon les performances enregistrées
- **Export PDF** — plan consultable hors ligne
- **Dashboard** — avancement du plan, temps restant, état de forme, météo
- **Suivi & statistiques** — historique des séances, performances passées, répartition par zones d'intensité
- **Administration** — gestion des comptes, ajustement des paramètres de l'algorithme, supervision des plans générés

## Stack technique

- **Backend** : **Symfony (PHP)** pour les contrôleurs, les services métier, la sécurité et la génération des pages côté serveur.
- **Base de données** : **MySQL** (via Doctrine ORM)
- **Frontend principal** : **Twig**, intégré à Symfony, pour générer les vues HTML de l'application.
- **Frontend React** : un prototype séparé fondé sur **React** et **Vite** est également présent dans le dossier `frontend/` ; il n'est pas intégré au service Docker principal.
- **Design & UI** : **Tailwind CSS** pour une interface moderne, responsive et pensée Mobile-First.

## 📐 Documentation & conception

Toute la documentation de conception se trouve dans le dossier [`/docs`](./docs) :

| Fichier                                                        | Contenu                                                                  |
| -------------------------------------------------------------- | ------------------------------------------------------------------------ |
| `docs/cahier-des-charges.md`                                  | Cahier des charges fonctionnel et technique actualisé                    |
| `docs/presentation/cahier-des-charges-synthese.html`          | Version synthétique et imprimable du cahier des charges                  |
| `docs/user-stories.md`                                         | Epics et user stories du projet                                          |
| `docs/personas.md`                                             | Personas représentatifs des différents niveaux et objectifs              |
| `docs/presentation/personas-mon-allure-hd.png`                 | Planche graphique de présentation des quatre personas                    |
| `docs/architecture-services-symfony.md`                        | Contrat fonctionnel et technique du moteur de génération et d'adaptation |
| `docs/use-cases-roles-entites.md`                              | Cas d'utilisation, rôles et entités concernées                           |
| `docs/mcd-mon-allure-en.png` / `docs/mld-mon-allure-en.png`    | Modèles conceptuel et logique de données                                 |
| `docs/diagrams/dictionnaire-donnees-mon-allure-a4.svg`         | Dictionnaire de données                                                  |
| `docs/diagrams/diagramme-services-mon-allure.svg`              | Architecture des services métier                                         |
| `docs/diagrams/sequence-creation-plan-mon-allure.svg`          | Séquence de création d'un plan                                           |
| `docs/diagrams/diagram de flux complet Mon Allure.png`         | Flux fonctionnel complet de l'application                                |
| `docs/diagrams/diagramme  séquence  flux génération  plan.png` | Séquence du flux de génération d'un plan                                 |
| `docs/diagrams/ diagramme boucle d'adaptation .png`            | Boucle d'adaptation du plan                                              |

## 🗺️ Roadmap (Epics)

- [ ] **Epic 1** — Authentification & compte
- [ ] **Epic 2** — Profil & calibrage sportif
- [ ] **Epic 3** — Génération du plan d'entraînement
- [ ] **Epic 4** — Dashboard utilisateur
- [ ] **Epic 5** — Suivi & statistiques
- [ ] **Epic 6** — Administration & algorithme

Le détail de chaque Epic, découpé en User Stories et tickets techniques, est disponible dans les [Issues GitHub](../../issues) du repository.

## 🚀 Installation avec Docker

```bash
# Cloner le projet
git clone <url-du-repo>
cd mon_allure

# Facultatif : personnaliser les ports et identifiants de développement
cp .env.docker.example .env

# Construire et démarrer Symfony et MySQL
docker compose up --build -d

# Créer les tables à partir des migrations Doctrine
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

L'application est ensuite accessible sur [http://localhost:8080](http://localhost:8080).

Commandes utiles :

```bash
# Afficher les journaux
docker compose logs -f app

# Recompiler Tailwind après une modification des styles
docker compose exec app php bin/console tailwind:build

# Exécuter les tests
docker compose exec app php bin/phpunit

# Arrêter les conteneurs
docker compose down

# Supprimer également la base MySQL Docker pour repartir de zéro
docker compose down --volumes
```

La dernière commande supprime les données locales stockées par Docker.

## 📌 Statut du projet

🚧 En cours de développement. Les éléments ci-dessus décrivent la cible fonctionnelle du projet et ne sont pas nécessairement tous disponibles dans l'application actuelle.

## 📄 Licence

\_
