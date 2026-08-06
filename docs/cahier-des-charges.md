# Cahier des charges — Mon Allure

**Version :** 2.0  
**Mise à jour :** 4 août 2026  
**Statut :** projet en cours de développement  
**Référence métier :** `docs/architecture-services-symfony.md`

## 1. Présentation du projet

### 1.1 Synopsis

Mon Allure est une application web Mobile-First destinée à la création et au suivi de plans d'entraînement personnalisés pour la course à pied sur route et le trail.

L'application exploite le profil physiologique du coureur, son niveau, son objectif et les paramètres du moteur afin de construire un programme cohérent. Après l'enregistrement des performances, elle analyse la réalisation des séances et peut ajuster les séances futures à la fin de chaque bloc complet de trois semaines.

Mon Allure est un outil d'aide à la planification. Il ne remplace ni un avis médical ni l'accompagnement d'un professionnel.

### 1.2 Problématique

Les coureurs ne disposent pas toujours des repères nécessaires pour choisir une charge, une allure et une progression adaptées. Les programmes génériques prennent rarement en compte simultanément :

- le niveau physiologique réel ;
- la nature et la date de l'objectif ;
- le terrain et le dénivelé ;
- les séances réellement réalisées ou manquées ;
- les exigences de progression, de récupération et d'affûtage.

Mon Allure centralise le profil sportif, l'objectif, la génération du plan, le calendrier et le suivi des performances.

### 1.3 Public cible

L'application s'adresse aux coureurs adultes, du débutant au pratiquant expérimenté :

- personne souhaitant courir plus longtemps pour retrouver la forme ;
- coureur loisir préparant une distance sans objectif chronométrique ;
- coureur expérimenté visant un temps sur une épreuve ;
- pratiquant sur route préparant un trail avec dénivelé.

Ces profils sont détaillés dans `docs/personas.md` et synthétisés dans `docs/presentation/personas-mon-allure-hd.png`.

### 1.4 Contexte d'utilisation

L'interface est conçue en priorité pour le mobile. Le coureur doit pouvoir consulter sa prochaine séance, parcourir son calendrier, comprendre l'effort demandé, enregistrer son résultat et suivre sa progression.

## 2. Objectifs

### 2.1 Objectifs fonctionnels

- créer et sécuriser un compte utilisateur ;
- recueillir l'âge, la VMA, la FCM, la FCR facultative et la VO2max facultative ;
- proposer les niveaux Découverte, Intermédiaire et Performance ;
- prendre en charge un objectif de durée, de distance ou de course chronométrée ;
- gérer les terrains route et trail ainsi qu'un D+ cible ;
- calculer la durée et la faisabilité du plan ;
- générer des séances datées avec volume, intensité et zones de travail ;
- consulter le plan dans un calendrier ;
- enregistrer les performances réalisées ;
- adapter uniquement les séances futures encore planifiées ;
- suivre la progression et exporter le plan en PDF ;
- administrer les comptes, les plans et les paramètres du moteur.

### 2.2 Objectifs de qualité

- interface claire et utilisable sur mobile ;
- règles métier isolées des contrôleurs et de la persistance ;
- génération cohérente et transactionnelle ;
- protection des données personnelles et sportives ;
- paramètres du moteur configurables et testables ;
- aucune modification rétroactive des séances terminées.

## 3. Périmètre du MVP

Le produit minimum viable doit permettre de :

1. créer un compte et se connecter ;
2. compléter un profil sportif valide ;
3. sélectionner un pôle de pratique ;
4. définir un objectif et une date cible ;
5. générer un premier plan personnalisé ;
6. consulter les séances dans un calendrier ;
7. consulter le détail d'une séance ;
8. enregistrer sa réalisation ou sa non-réalisation ;
9. visualiser l'avancement du plan.

Les statistiques avancées, la météo, le partage, l'export PDF et l'administration complète pourront être livrés après le MVP.

## 4. Acteurs et droits

### Visiteur

- consulter les pages publiques ;
- créer un compte ;
- se connecter.

### Utilisateur authentifié

- consulter et modifier son profil ;
- créer et consulter ses propres plans ;
- consulter ses séances et zones d'intensité ;
- enregistrer ses performances ;
- consulter ses statistiques et exporter son plan ;
- ajouter un commentaire lorsque la fonctionnalité est disponible.

### Administrateur

- consulter, désactiver ou supprimer des comptes ;
- modifier les paramètres numériques du moteur ;
- superviser les plans générés ;
- modérer ou supprimer les commentaires.

Un utilisateur ne doit jamais accéder aux données d'un autre utilisateur.

## 5. Spécifications fonctionnelles

### 5.1 Authentification et profil

Le compte comprend une adresse électronique unique, un pseudo unique et un mot de passe haché. Le système assure l'inscription, la connexion, la déconnexion et les droits `ROLE_USER` et `ROLE_ADMIN`.

| Donnée du profil | Obligation pour générer | Validation principale |
|---|---|---|
| Prénom et nom | Données de profil | Texte valide |
| Âge | Non bloquant | Valeur positive et plausible |
| VMA | Oui | Valeur strictement positive |
| FCM | Oui | Valeur strictement positive |
| FCR | Non | Positive et inférieure à la FCM |
| VO2max | Non | Enrichit l'analyse |

Une VMA ou une FCM absente ou invalide produit `insufficient_data` et bloque la génération. Une donnée seulement atypique peut déclencher un avertissement sans être automatiquement rejetée.

### 5.2 Choix du pôle

Le pôle est choisi à la création du plan et impose le nombre de séances :

| Pôle | Semaine normale | Régénération |
|---|---:|---:|
| Découverte (`discovery`) | 2 | 2 |
| Intermédiaire (`intermediate`) | 3 | 2 |
| Performance (`performance`) | 5 | 4 |

L'utilisateur ne choisit initialement ni le nombre de séances ni les jours disponibles. Le moteur construit le calendrier. Un déplacement manuel contrôlé pourra être proposé ultérieurement.

### 5.3 Objectif d'entraînement

| Exemple | Type | Valeur | Unité | Durée cible |
|---|---|---:|---|---:|
| Courir 60 minutes | `duration` | 60 | `min` | Non applicable |
| Parcourir 10 km | `distance` | 10 | `km` | Non applicable |
| Courir 10 km en 40 minutes | `race` | 10 | `km` | 2 400 secondes |

La demande contient aussi un nom, un pôle, un terrain `route` ou `trail`, un D+ facultatif et une date cible obligatoire.

La date de début est le premier lundi strictement postérieur à la validation, dans le fuseau `Europe/Paris`. La date cible doit être ultérieure.

### 5.4 Faisabilité

Le moteur attribue un indicateur explicable :

- `below_current_level` : inférieur au niveau estimé, sans blocage ;
- `realistic` : cohérent ;
- `ambitious` : exigeant mais envisageable ;
- `optimistic` : très exigeant ;
- `dangerous` : incompatible avec les limites de sécurité ;
- `insufficient_data` : données insuffisantes.

Cet indicateur repose sur des règles configurables. Il ne doit pas être présenté comme un diagnostic médical ni comme un « score IA » opaque.

### 5.5 Génération du plan

La génération prend en compte le profil, le pôle, l'objectif, le terrain, le dénivelé, la durée disponible, les zones d'intensité et les paramètres de progression, régénération et affûtage.

Une séance peut comporter une date, une semaine, un type, un titre, des consignes, une durée, une distance, un D+, un coefficient VMA, une zone cardiaque et une répartition entre zones.

Types prévus : endurance, actif, seuil, VMA, sortie longue, récupération, répétitions en côte, trail et événement cible. Un jour de repos correspond à l'absence de séance.

La création du plan et de ses séances est atomique : aucun plan partiel ne doit être conservé en cas d'erreur.

### 5.6 Calendrier et consultation

Le coureur consulte le mois, la semaine, le détail des séances, les zones ciblées et les états `planned`, `partially_done`, `done` ou `missed`. L'interface affiche aussi la semaine courante et le temps restant avant l'objectif.

Les codes visuels doivent être contrastés et ne pas transmettre une information uniquement par la couleur.

### 5.7 Performances et adaptation

Une performance liée à une séance peut contenir la distance, la durée, le D+, la fréquence cardiaque moyenne, un commentaire et le taux de réalisation.

Chaque performance entraîne une évaluation. L'adaptation effective intervient seulement à la fin d'un bloc complet de trois semaines :

- taux du bloc ≥ 80 % et au plus deux séances manquées : charge future × `1,05` ;
- taux du bloc < 80 % ou plus de deux séances manquées : charge future × `0,95`.

L'adaptation :

- modifie uniquement les séances futures `planned` ;
- ne modifie jamais les séances terminées ou manquées validées ;
- ne déplace ni la date cible ni l'événement final ;
- respecte une progression maximale de 10 % ;
- préserve les règles de récupération, d'enchaînement et de D+.

Un bloc final incomplet ne déclenche pas d'adaptation.

### 5.8 Suivi, statistiques et export

La cible comprend l'historique des séances, l'évolution des performances, la répartition par zones, l'avancement du plan, un état de forme et l'export PDF du plan avec ses séances. Le profil et l'historique complet des performances sont exclus de cet export.

La météo et le partage du bilan sont secondaires et hors MVP.

### 5.9 Administration

L'administrateur peut ajuster les paramètres sans modifier le code. Les changements s'appliquent aux nouvelles générations et adaptations ; les valeurs déjà calculées d'un plan ne sont pas recalculées automatiquement.

## 6. Sécurité de la progression

Ordre de priorité du moteur :

1. sécurité ;
2. affûtage ;
3. régénération ;
4. adaptation ;
5. progression normale.

Une semaine de régénération intervient toutes les quatre semaines avec un facteur de charge de `0,70`. La sortie longue ne dépasse pas 40 % du volume hebdomadaire. Les plafonds de progression, d'intensité et de D+ sont configurables.

## 7. Spécifications techniques actualisées

### 7.1 Stack retenue

- **PHP :** 8.4 ou supérieur ;
- **Framework :** Symfony 8.1 ;
- **Interface principale :** Twig, Symfony UX, Turbo, Stimulus et AssetMapper ;
- **Design :** Tailwind CSS, Mobile-First, bleu nuit et cyan ;
- **Base de données :** MySQL 8.0 ;
- **Persistance :** Doctrine ORM et Doctrine Migrations ;
- **Serveur :** Apache avec PHP 8.4 ;
- **Conteneurisation :** Docker Compose ;
- **Tests :** PHPUnit 13 ;
- **Logs :** Monolog.

Un prototype React 19/Vite 8 existe dans `frontend/`, mais n'est pas intégré au service Docker principal. Il ne constitue pas l'interface de référence.

Le projet n'est pas qualifié de PWA tant qu'un manifeste, un service worker et un fonctionnement hors ligne ne sont pas intégrés et testés.

### 7.2 Architecture métier

- les contrôleurs gèrent HTTP et la validation d'entrée, sans calcul sportif ;
- les calculs purs n'accèdent pas à Doctrine ;
- `TrainingPlanGeneratorService` orchestre des services spécialisés ;
- `AlgorithmParameterProvider` centralise les réglages typés ;
- la génération complète est transactionnelle ;
- des enums PHP encadrent les valeurs métier stockées en texte.

### 7.3 Données principales

| Entité | Responsabilité |
|---|---|
| `User` | Authentification, rôles et propriété |
| `Profile` | Identité et données physiologiques |
| `TrainingPlan` | Objectif, pôle, dates, faisabilité et progression |
| `Session` | Calendrier, volume, intensité, D+ et statut |
| `Performance` | Résultat réellement enregistré |
| `IntensityZone` | Bornes VMA et cardiaques Z1 à Z5 |
| `SessionIntensityZone` | Répartition d'une séance entre les zones |
| `AlgorithmParameter` | Réglages numériques du moteur |
| `Comment` | Échange lié à une séance ou un plan |

Le contrat prévoit encore `training_plan.target_duration_sec` pour l'objectif `race` et `performance.completion_rate` pour le taux de réalisation.

### 7.4 Interfaces HTTP

Les routes HTML présentes couvrent notamment l'inscription, la connexion, l'accueil, le calendrier et les vues d'entraînement. La cible API prévoit des contrôleurs pour créer et consulter un plan, enregistrer une performance, adapter le plan, déplacer une séance, exporter un PDF et administrer les ressources.

Les URI définitives devront être documentées et testées. Une création réussie retourne `201 Created` — et non `21 Created` comme indiqué dans l'ancien document.

## 8. Contraintes non fonctionnelles

### 8.1 Ergonomie et accessibilité

- conception Mobile-First utilisable dès 320 px ;
- navigation au clavier et focus visible ;
- formulaires avec libellés et erreurs explicites ;
- contraste suffisant et information non dépendante de la couleur ;
- unités toujours visibles avec les données sportives.

### 8.2 Sécurité

- mots de passe hachés avec Symfony PasswordHasher ;
- protection CSRF des formulaires avec état ;
- validation serveur obligatoire ;
- contrôle d'accès par rôle et propriétaire ;
- cookies sécurisés en production ;
- aucun secret, jeton ou donnée sensible dans les logs ;
- secrets fournis par variables d'environnement ;
- dépendances contrôlées régulièrement.

### 8.3 RGPD

- limiter la collecte aux données nécessaires ;
- informer sur la finalité des données physiologiques ;
- permettre l'accès, la rectification et la suppression ;
- définir la durée de conservation ;
- protéger la base et les sauvegardes ;
- ne conserver aucune donnée sans base légale après suppression ;
- anonymiser réellement toute donnée statistique conservée.

La politique de confidentialité, les mentions légales et la procédure d'exercice des droits restent à produire avant une mise en production publique.

### 8.4 Performance et compatibilité

Les objectifs chiffrés doivent être établis après mesure sur un environnement représentatif. Les anciennes hypothèses de 500 utilisateurs actifs, 50 RPS, Redis et latence inférieure à 100 ms ne sont pas retenues sans étude de volumétrie ni test de charge.

L'application vise les versions récentes de Chrome, Firefox, Safari et Edge, sur mobile comme sur ordinateur. Les requêtes doivent éviter les problèmes N+1 et les pages principales rester rapidement utilisables sur une connexion mobile.

## 9. Tests et acceptation

### 9.1 Tests attendus

- calculs d'allure, zones, durée, charge et dénivelé ;
- limites de faisabilité et profils incomplets ;
- génération pour chaque pôle et chaque objectif ;
- semaines normales, de régénération et d'affûtage ;
- transaction et retour arrière ;
- adaptation sur blocs complets et incomplets ;
- immutabilité des séances terminées ;
- authentification, autorisations et propriété des données ;
- calendrier et parcours critiques sur mobile.

### 9.2 Critères d'acceptation du MVP

- création de compte et connexion fonctionnelles ;
- profil valide et erreurs compréhensibles ;
- validation des trois types d'objectifs ;
- génération cohérente selon le pôle ;
- séances visibles et consultables dans le calendrier ;
- isolation stricte des données entre utilisateurs ;
- enregistrement d'une réalisation et mise à jour du suivi ;
- aucune persistance partielle en cas d'erreur ;
- réussite des tests automatisés critiques.

## 10. Déploiement et exploitation

L'environnement local utilise Docker Compose avec un conteneur PHP 8.4/Apache, un conteneur MySQL 8.0 et des volumes persistants.

Avant la production, il faudra définir une chaîne CI, les environnements, les migrations et retours arrière, les sauvegardes et tests de restauration, la supervision, la rotation des logs, HTTPS et la gestion des secrets.

## 11. Hors périmètre du MVP

- coaching médical ou diagnostic ;
- connexion directe aux montres et plateformes sportives ;
- suivi GPS en direct et calcul d'itinéraire ;
- application mobile native ;
- fonctionnement hors ligne complet de type PWA ;
- recommandations par IA générative ;
- réseau social, classement, paiement ou abonnement.

## 12. Livrables et évolutions

### Livrables

- cahier des charges actualisé ;
- user stories et cas d'utilisation ;
- personas et planche de présentation ;
- MCD, MLD et dictionnaire de données ;
- diagrammes de services, de flux et de séquence ;
- contrat technique Symfony ;
- code, migrations, tests et procédure Docker.

### Évolutions envisagées

- export PDF et statistiques enrichies ;
- météo contextualisée et partage volontaire ;
- déplacement contrôlé des séances ;
- administration complète ;
- connecteurs vers des services sportifs ;
- étude d'une évolution vers une PWA ou une application mobile.

Toute évolution devra préserver la sécurité, la propriété des données et l'immutabilité des séances terminées.
