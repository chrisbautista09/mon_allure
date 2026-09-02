# 🏃 Mon Allure

Mon Allure est une application web de suivi de course à pied. Elle génère un plan d'entraînement personnalisé à partir du profil physiologique et de l'objectif du coureur, puis suit sa progression à partir des séances réalisées.

## Fonctionnalités disponibles

- inscription, authentification et contrôle des accès utilisateur/administrateur ;
- profil physiologique : VMA, fréquences cardiaques, âge et données sportives ;
- définition d'un objectif et génération d'un plan hebdomadaire personnalisé ;
- tableau de bord avec progression, échéance, forme, météo et graphiques ;
- calendrier, saisie des performances, historique et bilan d'entraînement ;
- export PDF du plan ;
- espace administrateur : utilisateurs, plans générés, commentaires et paramètres de l'algorithme ;
- jeux de données de démonstration pour présenter plusieurs profils de coureurs.

## Architecture et choix techniques

| Couche | Technologies | Rôle |
|---|---|---|
| Serveur | PHP 8.4+, Symfony 8.1 | Contrôleurs, sécurité, formulaires et logique métier |
| Interface | Twig, Turbo, Stimulus, Tailwind CSS | Interface responsive rendue côté serveur et interactions progressives |
| Données | MySQL 8, Doctrine ORM, migrations | Persistance relationnelle et évolution contrôlée du schéma |
| Assets | AssetMapper et Importmap | Dépendances JavaScript sans chaîne de build Node.js |
| Visualisation | Chart.js 4.5.1 via Importmap | Graphiques du tableau de bord et des performances |
| Documents | Dompdf | Export des plans d'entraînement au format PDF |
| Tests | PHPUnit 13 | Tests unitaires, d'intégration et fonctionnels |
| Exécution | Docker Compose, Apache | Environnement reproductible pour l'application et MySQL |

### Pourquoi Chart.js via Importmap ?

Chart.js est importé directement dans [`backend/importmap.php`](backend/importmap.php), puis utilisé par les contrôleurs JavaScript de l'application. Ce choix reste cohérent avec Symfony AssetMapper : il évite d'ajouter npm, Vite ou Webpack uniquement pour les graphiques, réduit le nombre d'outils à installer et conserve un déploiement simple pour une interface Twig/Turbo.

La contrepartie est assumée : le cycle de vie des graphiques, notamment lors des navigations Turbo, est géré explicitement dans le code JavaScript plutôt que par une couche Symfony UX supplémentaire.

## Démarrage avec Docker

Prérequis : Docker Engine avec le plugin Docker Compose.

```bash
git clone <url-du-depot>
cd mon_allure
cp .env.docker.example .env
# Remplacer les valeurs de secret et de mots de passe dans .env
docker compose up --build -d
```

L'application est ensuite accessible sur <http://localhost:8080>. Au démarrage, le conteneur attend que MySQL soit disponible puis applique automatiquement les migrations Doctrine.

Commandes utiles :

```bash
# Suivre les journaux
docker compose logs -f app

# Créer un compte administrateur
docker compose exec app php bin/console app:create-admin admin@example.test 'MotDePasseSolide'

# Générer les comptes et historiques prévus pour la démonstration
docker compose exec app php bin/console app:demo-data:seed

# Réinitialiser ces données de démonstration
docker compose exec app php bin/console app:demo-data:reset

# Arrêter les conteneurs en conservant la base
docker compose down
```

Pour repartir avec une base vide, supprimer volontairement le volume avec `docker compose down -v`.

## Installation locale sans Docker

Prérequis : PHP 8.4+, Composer, MySQL 8 et Symfony CLI (recommandé).

```bash
cd backend
composer install
cp .env .env.local
# Adapter DATABASE_URL dans .env.local
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console tailwind:build
symfony server:start
```

## Tests et contrôles

Depuis `backend/` :

```bash
php bin/phpunit
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
```

La suite couvre les services métier, les entités, les gabarits et les principaux parcours HTTP. Les migrations constituent la source de vérité du schéma de base de données.

Dernière vérification locale (28 août 2026) : **463 tests et 2 783 assertions, tous validés**.

## Structure du dépôt

```text
mon_allure/
├── backend/            application Symfony
│   ├── assets/         JavaScript, Stimulus et styles
│   ├── config/         configuration Symfony et Importmap
│   ├── migrations/     migrations Doctrine
│   ├── src/            code PHP métier et HTTP
│   ├── templates/      vues Twig
│   └── tests/          tests PHPUnit
├── docs/               conception fonctionnelle et diagrammes
├── compose.yaml        orchestration application + MySQL
└── README.md
```

Le dossier `frontend/` présent dans certains environnements est un vestige d'un prototype React. L'interface active est celle de Symfony/Twig ; une SPA séparée reste une évolution possible, mais elle n'est pas nécessaire au fonctionnement actuel.

## Documentation du projet

Les user stories, règles métier, choix météo et diagrammes sont regroupés dans [`docs/`](docs/). Le dossier projet de formation reste un document de référence figé ; ce README décrit l'état exécutable le plus récent du dépôt.

## Statut

Le socle fonctionnel du projet est opérationnel. Les évolutions envisagées peuvent notamment porter sur l'industrialisation du déploiement, l'observabilité, l'enrichissement des algorithmes et une éventuelle application cliente dédiée.

## Licence

Projet de formation — tous droits réservés.
