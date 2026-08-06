# Architecture des services Symfony — Mon Allure

## 1. Objet du document

Ce document constitue le contrat fonctionnel et technique du moteur de génération de Mon Allure.

Il décrit :

- les services Symfony à créer ;
- les responsabilités de chaque service ;
- les règles métier validées ;
- les entités et tables utilisées ;
- les paramètres configurables de l'algorithme ;
- le flux de génération, de validation et d'adaptation d'un plan.

Les diagrammes historiques ne sont pas utilisés comme référence dans ce document.

## 2. Principes d'architecture

- Les contrôleurs gèrent HTTP, la validation des entrées et la sérialisation, mais aucun calcul d'entraînement.
- `TrainingPlanGeneratorService` orchestre les services spécialisés sans devenir un service monolithique.
- Les calculs purs n'accèdent pas à Doctrine.
- La lecture des réglages passe exclusivement par `AlgorithmParameterProvider`.
- La génération complète d'un plan et de ses séances est atomique et transactionnelle.
- Les séances déjà terminées ne sont jamais modifiées par une adaptation.
- Les valeurs catégorielles restent stockées dans les colonnes `VARCHAR` existantes.
- Des enums PHP métier encadrent ces valeurs sans mapping Doctrine et sans modification du type des colonnes.
- Les valeurs calculées d'un plan existant ne sont pas recalculées automatiquement lorsqu'un administrateur modifie un paramètre.

## 3. Modèle de données concerné

### 3.1 Tables existantes utilisées

| Table | Rôle dans le moteur |
|---|---|
| `user` | Propriétaire du profil, des plans et des performances |
| `profile` | VMA, FCM, FCR facultative, VO2max et âge |
| `training_plan` | Objectif, pôle, dates, durée, faisabilité et progression du plan |
| `session` | Séances prévues, calendrier, type, volume, intensité, D+ et statut |
| `performance` | Résultats réellement déclarés pour une séance |
| `intensity_zone` | Bornes VMA et cardiaques de Z1 à Z5 |
| `session_intensity_zone` | Répartition détaillée du temps d'une séance entre les zones |
| `algorithm_parameter` | Réglages numériques modifiables du moteur |

### 3.2 Évolutions minimales prévues

Aucune nouvelle table n'est nécessaire.

Deux colonnes seulement devront être ajoutées par une migration non destructive :

| Table | Colonne | Type proposé | Utilité |
|---|---|---|---|
| `training_plan` | `target_duration_sec` | `INT NULL` | Temps visé pour un objectif `race` |
| `performance` | `completion_rate` | `DOUBLE NULL` | Taux de réalisation compris entre `0.00` et `1.00` |

Les lignes historiques peuvent conserver `NULL` pour ces deux colonnes.

### 3.3 Relations Doctrine importantes

- `User` possède un `Profile`.
- `User` possède plusieurs `TrainingPlan`.
- `TrainingPlan` possède plusieurs `Session`.
- `Session` possède des `Performance`.
- `Session` possède des `SessionIntensityZone`.
- `SessionIntensityZone` référence une `IntensityZone`.
- `TrainingPlan::addSession()` renseigne également `Session::setTrainingPlan()`.
- La cascade de persistance de `TrainingPlan` vers `Session` est conservée.

## 4. Valeurs métier encadrées par des enums PHP

Les enums sont utilisés dans les DTO et les services. Les entités peuvent continuer à stocker leur valeur textuelle.

### 4.1 `PoleType`

```text
discovery
intermediate
performance
```

### 4.2 `TargetType`

```text
duration
distance
race
```

### 4.3 `TargetUnit`

```text
min
km
```

### 4.4 `TerrainType`

```text
route
trail
```

### 4.5 `SessionType`

```text
endurance
active
threshold
vma
long_run
recovery
hill_repeats
trail
goal_event
```

`rest` n'est pas une séance : il correspond à l'absence de ligne `session` pour la journée.

### 4.6 `SessionStatus`

```text
planned
partially_done
done
missed
```

### 4.7 `FeasibilityIndicator`

```text
below_current_level
realistic
ambitious
optimistic
dangerous
insufficient_data
```

`below_current_level` informe l'utilisateur sans bloquer la génération.

## 5. Données d'entrée de la génération

### 5.1 `GenerateTrainingPlanRequest`

Le DTO de génération contient au minimum :

```php
final readonly class GenerateTrainingPlanRequest
{
    public function __construct(
        public string $name,
        public TargetType $targetType,
        public float $targetValue,
        public TargetUnit $targetUnit,
        public PoleType $poleType,
        public TerrainType $terrainType,
        public ?int $elevationTargetDPlus,
        public ?int $targetDurationSec,
        public DateTimeImmutable $targetDate,
    ) {
    }
}
```

Le DTO ne contient ni `startDate`, ni `sessionsPerWeek`, ni `availableDays`.

- `startDate` est calculée automatiquement.
- Le pôle impose le nombre de séances.
- Le moteur choisit le calendrier ; l'utilisateur s'adapte au programme proposé.

### 5.2 Contrat des trois objectifs

| Objectif | `targetType` | `targetValue` | `targetUnit` | `targetDurationSec` |
|---|---|---:|---|---:|
| Courir pendant 60 minutes | `duration` | `60` | `min` | `NULL` |
| Parcourir 10 km | `distance` | `10` | `km` | `NULL` |
| Courir 10 km en 40 minutes | `race` | `10` | `km` | `2400` |

Validations conditionnelles :

- `duration` exige une valeur positive en minutes et interdit `targetDurationSec`.
- `distance` exige une valeur positive en kilomètres et interdit `targetDurationSec`.
- `race` exige une distance positive en kilomètres et un `targetDurationSec` positif.
- `targetDate` est obligatoire et strictement postérieure à la `startDate` calculée.
- `targetDate` est enregistrée dans la propriété Doctrine existante `TrainingPlan.endDate` ; le nom métier reste `targetDate` afin d'exprimer clairement son rôle.
- Le D+ est positif ou nul ; il est facultatif sur route.

## 6. Services de paramètres et de validation du profil

### 6.1 `AlgorithmParameterProvider`

**Responsabilité**

Centraliser la lecture typée des lignes de `algorithm_parameter`.

**Méthodes principales**

```php
public function getFloat(string $key): float;
public function getInt(string $key): int;
public function getBool(string $key): bool;
public function getAll(): array;
```

**Règles**

- Le provider dépend de `AlgorithmParameterRepository`.
- Les autres services n'interrogent pas directement ce repository.
- Une clé obligatoire absente déclenche `AlgorithmParameterNotFoundException`.
- Une mise en cache Symfony pourra être ajoutée.
- La table actuelle stocke toutes les valeurs sous forme numérique ; les conversions sont réalisées par le provider.

**Table liée** : `algorithm_parameter`.

### 6.2 `ProfileTrainingDataValidator`

**Responsabilité**

Vérifier que le profil contient les données minimales nécessaires.

**Règles**

- VMA obligatoire et strictement positive.
- FCM obligatoire et strictement positive.
- FCR facultative, strictement positive lorsqu'elle est fournie et toujours inférieure à la FCM.
- Des plages de plausibilité peuvent produire un avertissement : VMA environ 5 à 30 km/h, FCM environ 100 à 230 bpm, FCR environ 30 à 120 bpm.
- Une valeur seulement atypique n'est pas automatiquement rejetée.
- Une VMA ou une FCM absente/invalide produit `insufficient_data` et bloque la génération.
- L'absence de performance antérieure ne bloque pas la génération, mais réduit le niveau de confiance.
- VO2max et âge enrichissent l'analyse sans être obligatoires pour une première génération.

**Tables liées** : `profile`, `performance` en lecture facultative.

## 7. Services de dates et de durée

### 7.1 `PlanStartDateCalculator`

**Responsabilité**

Calculer le début administratif du plan.

**Règles**

- Le déclencheur est la validation du pôle et la création du plan.
- `startDate` est le premier lundi strictement postérieur à cette validation.
- Une création effectuée un lundi conduit au lundi suivant.
- Le calcul utilise le fuseau `Europe/Paris`.
- `startDate` marque le début de la première semaine, pas nécessairement la première séance.

**Table alimentée** : `training_plan.start_date`.

La date cible est enregistrée parallèlement dans `training_plan.end_date` sans renommer la colonne existante.

### 7.2 `PlanDurationCalculator`

**Responsabilité**

Calculer la période disponible et la durée recommandée.

**Période disponible**

```php
$availableDays = $startDate->diff($targetDate)->days + 1;
$availableWeeks = (int) ceil($availableDays / 7);
```

**Résultat recommandé**

```php
final readonly class RecommendedPlanDuration
{
    public function __construct(
        public int $minimumWeeks,
        public int $maximumWeeks,
        public int $idealWeeks,
    ) {
    }
}
```

La plage dépend du pôle, de l'objectif, du niveau actuel, de la distance et, pour `race`, du gain chronométrique demandé.

#### Objectif de durée

| Progression demandée | Discovery | Intermediate | Performance |
|---|---:|---:|---:|
| jusqu'à +25 % | 8 sem. | 6 sem. | 4 sem. |
| +26 à +50 % | 10 sem. | 8 sem. | 6 sem. |
| +51 à +100 % | 14 sem. | 10 sem. | 8 sem. |
| plus de +100 % | 18 à 24 sem. | 14 à 20 sem. | 12 à 16 sem. |

#### Objectif de distance sans chrono

| Distance | Discovery | Intermediate | Performance |
|---|---:|---:|---:|
| 5 km | 8 à 12 sem. | 6 à 10 sem. | 4 à 8 sem. |
| 10 km | 12 à 16 sem. | 10 à 14 sem. | 8 à 12 sem. |
| Semi-marathon | 16 à 24 sem. | 14 à 20 sem. | 12 à 18 sem. |
| Marathon | 24 sem. ou plus | 18 à 26 sem. | 16 à 24 sem. |

#### Objectif `race`

| Épreuve | Discovery | Intermediate | Performance |
|---|---:|---:|---:|
| 5 km | 10 à 14 sem. | 8 à 12 sem. | 6 à 10 sem. |
| 10 km | 12 à 18 sem. | 10 à 16 sem. | 8 à 14 sem. |
| Semi-marathon | 18 à 24 sem. | 14 à 20 sem. | 12 à 18 sem. |
| Marathon | 24 sem. ou plus | 18 à 26 sem. | 16 à 24 sem. |

Correction selon le gain chronométrique demandé :

```text
gain <= 3 %      : durée de base
gain de 3 à 6 %  : durée de base + 2 semaines
gain de 6 à 10 % : durée de base + 4 semaines
gain > 10 %      : faisabilité optimistic ou dangerous
```

Une période supérieure au maximum recommandé reçoit une phase de développement général ou de maintien ; elle n'est pas rejetée.

**Tables lues** : `profile`, `performance`, `algorithm_parameter` via le provider.

**Table alimentée** : `training_plan.duration_weeks`.

## 8. Service de faisabilité

### 8.1 `FeasibilityService`

**Responsabilité**

Évaluer l'objectif à partir du profil, des performances disponibles et de la période avant la cible.

**Résultat**

```php
final readonly class FeasibilityResult
{
    public function __construct(
        public FeasibilityIndicator $indicator,
        public float $score,
        public int $recommendedDurationWeeks,
        public array $warnings,
    ) {
    }
}
```

**Comparaison de durée**

```php
$durationRatio = $availableWeeks / $minimumRecommendedWeeks;
```

| Condition | Indicateur |
|---|---|
| objectif déjà maîtrisé | `below_current_level` |
| ratio >= 1.00 | `realistic` |
| ratio >= 0.85 | `ambitious` |
| ratio >= 0.70 | `optimistic` |
| ratio < 0.70 | `dangerous` |
| VMA ou FCM insuffisante | `insufficient_data` |

**Ordre d'évaluation**

1. Vérifier les données physiologiques minimales.
2. Vérifier si l'objectif est déjà maîtrisé.
3. Calculer la durée recommandée.
4. Calculer la période disponible.
5. Comparer les deux durées.
6. Produire l'indicateur, le score et les avertissements.

Un objectif `below_current_level` reste générable. Un objectif risqué doit produire une information explicite ; la politique exacte de blocage HTTP d'un objectif `dangerous` pourra être définie au niveau applicatif.

**Tables lues** : `profile`, `performance`, `algorithm_parameter` via le provider.

**Table alimentée** : `training_plan.feasibility_indicator`.

## 9. Services d'allure et de zones

### 9.1 `PaceCalculatorService`

**Responsabilité**

Effectuer les calculs purs de vitesse, allure, durée et distance.

**Méthodes**

```php
public function calculateSpeedFromVma(float $vma, float $coefficient): float;
public function speedToPace(float $speedKmh): float;
public function calculateDurationSeconds(float $distanceKm, float $speedKmh): int;
public function calculateDistanceKm(int $durationMinutes, float $speedKmh): float;
```

**Règles**

- Aucun accès Doctrine.
- Une vitesse nulle ou négative déclenche une exception métier.
- Les coefficients proviennent d'`AlgorithmParameterProvider` avant l'appel.
- L'allure fixe n'est pas utilisée comme contrainte principale sur une séance trail ou une séance de côte.

**Tables liées indirectement** : `profile`, `session`, `algorithm_parameter`.

### 9.2 `IntensityZoneService`

**Responsabilité**

Déterminer les zones de travail et les plages cardiaques/vitesse personnalisées.

#### Zones validées

| Zone | Usage | % VMA | % FCM |
|---|---|---:|---:|
| Z1 | récupération | 50 à 65 % | 50 à 60 % |
| Z2 | endurance fondamentale | 65 à 75 % | 60 à 70 % |
| Z3 | endurance active | 75 à 85 % | 70 à 80 % |
| Z4 | seuil | 85 à 95 % | 80 à 90 % |
| Z5 | VMA | 95 à 105 % | 90 à 100 % |

#### Calcul cardiaque sans FCR

```php
$targetHr = $fcm * $zonePercent;
```

#### Calcul cardiaque avec FCR

```php
$reserve = $fcm - $fcr;
$targetHr = $fcr + ($reserve * $zonePercent);
```

#### Correspondance séances/zones

| Séance | Zone principale | Zones secondaires possibles |
|---|---|---|
| `recovery` | Z1 | aucune |
| `endurance` | Z2 | Z1 |
| `active` | Z3 | Z2 |
| `threshold` | Z4 | Z2 |
| `vma` | Z5 | Z1, Z2 |
| `long_run` | Z2 | Z3 |
| `hill_repeats` | Z4 | Z5, Z2 |
| `trail` | Z2 | Z3, Z4 |

`SessionIntensityZone` est la source détaillée. `Session.plannedFcmZone` conserve la zone principale résumée. La somme des `durationPercent` d'une séance doit être égale à 100.

**Tables lues** : `profile`, `intensity_zone`.

**Tables alimentées** : `session`, `session_intensity_zone`.

## 10. Services de charge hebdomadaire

### 10.1 `WeeklyLoadService`

**Responsabilité**

Construire la progression semaine par semaine avant de créer les séances.

**Résultat intermédiaire**

```php
final readonly class WeeklyTrainingLoad
{
    public function __construct(
        public int $weekIndex,
        public float $totalDistanceKm,
        public int $totalDurationMinutes,
        public int $sessionCount,
        public bool $recoveryWeek,
        public bool $taperWeek,
        public float $enduranceRatio,
        public float $qualityRatio,
        public int $elevationDPlus,
    ) {
    }
}
```

### 10.2 Nombre de séances par pôle

| Pôle | Semaine normale | Régénération |
|---|---:|---:|
| Discovery | 2 | 2 |
| Intermediate | 3 | 2 |
| Performance | 5 | 4 |

- Le nombre est imposé par le pôle ; l'utilisateur ne le choisit pas.
- La régénération intervient toutes les quatre semaines : 4, 8, 12, etc.
- La charge d'une semaine de régénération est multipliée par `0.70`.
- Discovery conserve deux séances, mais leur contenu peut être allégé.
- La sortie longue ne dépasse pas 40 % du volume hebdomadaire.
- La progression hebdomadaire générale ne dépasse pas 10 %.

### 10.3 Répartition endurance/qualitatif

Le calcul porte sur le temps réellement passé dans les zones :

```text
endurance  = Z1 + Z2
qualitatif = Z3 + Z4 + Z5
```

| Pôle | Endurance minimale | Qualitatif maximal |
|---|---:|---:|
| Discovery | 90 % | 10 % |
| Intermediate | 80 % | 20 % |
| Performance | 75 % | 25 % |

Le plafond qualitatif n'est pas une obligation à remplir. Les premières semaines, la régénération et l'affûtage peuvent rester nettement en dessous.

**Tables lues** : `profile`, `performance`, `algorithm_parameter` via le provider.

**Tables alimentées indirectement** : `training_plan`, `session`.

## 11. Services de dénivelé et de charge

### 11.1 `ElevationCalculatorService`

**Responsabilité**

Estimer l'impact du D+ sur le volume et la charge sans prétendre reconstituer précisément le profil du parcours.

#### Distance-effort

```php
$effortDistanceKm = $distanceKm
    + (($elevationDPlus / 100) * $equivalentKmPer100m);
```

Valeur initiale : 100 m D+ équivalent à 1 km-effort.

#### Densité de D+

```php
$elevationDensity = $elevationDPlus / $distanceKm;
```

| Densité | Profil indicatif |
|---:|---|
| moins de 10 m/km | peu vallonné |
| 10 à 25 m/km | vallonné |
| 25 à 50 m/km | très vallonné |
| plus de 50 m/km | montagneux ou fortes côtes |

#### Facteur de charge

```php
$elevationRatio = ($elevationDPlus / 100) / $distanceKm;
$elevationFactor = 1 + min(
    $maximumElevationBonus,
    $elevationRatio * $elevationLoadWeight,
);
```

Valeurs initiales : poids `0.50`, bonus maximal `0.50`, soit un facteur plafonné à `1.50`.

#### Limites assumées

- Seul le dénivelé positif est utilisé.
- Le D- et la technicité du terrain ne sont pas modélisés.
- Le D+ total ne permet pas d'imposer une allure fixe fiable.
- Les séances trail/côtes sont prescrites par durée, zones, répétitions et D+ cible.

**Tables lues/alimentées** : `training_plan`, `session`, `performance`.

### 11.2 `TrainingLoadCalculator`

**Responsabilité**

Calculer la charge d'une séance et d'une semaine.

```php
$baseLoad = $durationMinutes * $intensityCoefficient;
$totalLoad = $baseLoad * $elevationFactor;
```

**Règles**

- La progression du D+ est limitée séparément à 10 % par semaine.
- Distance, durée, D+ et qualitatif ne sont pas tous augmentés simultanément à leur maximum.
- Une hausse importante du D+ entraîne une progression plus modérée des autres dimensions.
- La charge d'une séance réalisée peut utiliser la durée, l'intensité observée et le D+ réel.
- Aucun accès Doctrine.

**Tables liées indirectement** : `session`, `performance`, `algorithm_parameter`.

## 12. Génération et calendrier des séances

### 12.1 `SessionGeneratorService`

**Responsabilité**

Transformer chaque `WeeklyTrainingLoad` en séances concrètes et datées.

**Données générées**

- semaine et jour ;
- date ;
- type, titre et description ;
- distance et durée prévues ;
- D+ prévu ;
- coefficient VMA ;
- zone principale ;
- répartition détaillée des zones ;
- statut initial `planned`.

### 12.2 Règles Discovery

- Deux séances en semaine normale.
- Au moins deux journées complètes sans entraînement entre les séances.
- Aucune autre contrainte de placement.

### 12.3 Règles Intermediate

- Trois séances en semaine normale.
- Au moins une journée complète sans entraînement entre deux séances.
- Une journée de repos obligatoire après une VMA.
- Une journée de repos obligatoire après une sortie longue.
- Structure habituelle : endurance, séance qualitative, sortie longue.

### 12.4 Règles Performance

- Cinq séances en semaine normale.
- Après `vma` ou `long_run`, le lendemain accepte seulement : repos, `endurance` ou `recovery`.
- Les types `active`, `threshold`, `vma`, `long_run`, `hill_repeats` et `trail` sont interdits le lendemain.
- Enchaînements interdits : `vma -> long_run` et `long_run -> vma`.
- Structure habituelle : deux séances endurance/récupération, une VMA ou côtes, une active ou seuil, une sortie longue.

### 12.5 Déplacement manuel

- Le moteur choisit les jours ; aucune disponibilité n'est demandée ni stockée.
- Discovery et Intermediate autorisent un déplacement si les intervalles restent valides.
- Performance autorise des permutations limitées.
- Toute permutation est refusée si le calendrier résultant enfreint une règle d'enchaînement.
- La date réellement déplacée reste enregistrée dans `session.date`.

**Tables alimentées** : `session`, `session_intensity_zone`.

### 12.6 `SessionScheduleValidator`

**Responsabilité**

Contrôler le calendrier après génération ou déplacement et avant persistance.

Il vérifie :

- le nombre de séances du pôle et du type de semaine ;
- les jours de repos ;
- les enchaînements interdits ;
- la somme des zones égale à 100 % ;
- l'absence de séance hors des dates du plan ;
- la présence unique de `goal_event` à la date cible ;
- le plafond de sortie longue ;
- les plafonds de charge, de qualitatif et de progression du D+.

**Tables contrôlées** : `training_plan`, `session`, `session_intensity_zone`.

## 13. Affûtage et objectif final

### 13.1 `TaperService`

**Responsabilité**

Construire l'allègement final en priorité sur la régénération.

| Pôle | Organisation |
|---|---|
| Discovery | dernière semaine : une séance légère + `goal_event` |
| Intermediate | une semaine, charge à 70 %, une séance légère + `goal_event` |
| Performance | avant-dernière semaine : 4 séances et 80 % ; dernière semaine : 2 séances légères + `goal_event`, charge à 60 % |

**Règles**

- L'affûtage remplace une régénération qui tomberait à la même période.
- Les coefficients d'affûtage concernent la préparation avant l'objectif, pas la charge de `goal_event`.
- Une petite quantité d'intensité peut être conservée sous forme de rappels courts.
- Aucune VMA complète ni vraie sortie longue la dernière semaine.

**Tables alimentées** : `session`, à partir de `training_plan.end_date`.

### 13.2 `GoalEventFactory`

**Responsabilité**

Créer l'unique séance finale `goal_event` à `TrainingPlan.endDate`.

| Objectif | Contenu de `goal_event` |
|---|---|
| `duration` | durée cible, sans distance imposée |
| `distance` | distance cible, sans temps imposé |
| `race` | distance cible et temps visé |

`goal_event` :

- compte comme effort dans le calendrier final ;
- remplace une séance, il ne s'ajoute pas au volume normal ;
- est exclu des calculs d'adaptation ;
- peut recevoir une `Performance` finale ;
- permet de mesurer la réussite de l'objectif.

**Tables liées** : `training_plan`, `session`, `performance`.

## 14. Orchestration et persistance

### 14.1 `TrainingPlanGeneratorService`

**Responsabilité**

Orchestrer la génération complète.

**Flux**

1. Recevoir l'utilisateur et le DTO validé.
2. Charger et valider le profil.
3. Charger les performances disponibles.
4. Calculer le lundi de départ.
5. Calculer la durée disponible et recommandée.
6. Évaluer la faisabilité.
7. Créer `TrainingPlan`.
8. Construire les charges hebdomadaires.
9. Appliquer régénération et affûtage.
10. Générer les séances et leurs zones.
11. Créer `goal_event`.
12. Valider le calendrier complet.
13. Associer chaque séance avec `TrainingPlan::addSession()`.
14. Persister le tout dans une transaction Doctrine.

**Méthode principale**

```php
public function generate(
    User $user,
    GenerateTrainingPlanRequest $request,
): TrainingPlan;
```

**Transaction**

Si une erreur survient pendant la génération ou la validation d'une séance, aucun plan incomplet ne reste en base.

**Tables lues** : `user`, `profile`, `performance`, `intensity_zone`, `algorithm_parameter`.

**Tables alimentées** : `training_plan`, `session`, `session_intensity_zone`.

## 15. Validation des performances

### 15.1 `PerformanceValidationService`

**Responsabilité**

Créer ou mettre à jour l'unique validation active d'une séance.

**Taux de réalisation**

| Affichage | `completionRate` | Statut de séance |
|---:|---:|---|
| 0 % | `0.00` | `missed` |
| 1 à 99 % | `0.01` à `0.99` | `partially_done` |
| 100 % | `1.00` | `done` |

**Règles**

- `completionRate` est compris entre `0.00` et `1.00`.
- L'interface convertit le pourcentage avec `percent / 100`.
- Distance et durée réelles sont positives ou nulles, jamais négatives.
- Une séance à 0 % peut enregistrer distance et durée à zéro.
- La correction d'une validation met à jour la `Performance` existante au lieu d'en créer une seconde.
- La relation Doctrine actuelle peut rester en `OneToMany` dans un premier temps ; l'unicité active est imposée par le service.

**Tables alimentées** : `performance`, `session`.

## 16. Analyse et adaptation

### 16.1 `PerformanceAnalyzer`

**Responsabilité**

Analyser des blocs complets et non chevauchants de trois semaines.

**Calcul hebdomadaire**

```php
$weeklyRate = array_sum($completionRates)
    / count($plannedSessions);
```

Chaque séance possède le même poids dans cette première version.

**Calcul du bloc**

```php
$blockRate = array_sum($weeklyRates) / 3;
```

L'analyse relève également :

- le nombre de séances à 0 % ;
- les séances partielles ;
- l'écart durée/distance/D+ prévu-réalisé ;
- la tendance ;
- les informations cardiaques disponibles.

Pour une séance vallonnée, la distance-effort complète la distance brute afin de ne pas pénaliser une distance plus courte avec davantage de D+.

**Tables lues** : `training_plan`, `session`, `performance`.

### 16.2 `AdaptationService`

**Responsabilité**

Décider de l'adaptation à la fin de chaque bloc complet : semaines 1-3, 4-6, 7-9, etc.

```text
taux du bloc >= 0.80 et séances manquées <= 2
    -> charge de référence future x 1.05

taux du bloc < 0.80 ou séances manquées > 2
    -> charge de référence future x 0.95
```

Il n'existe pas de zone de maintien. Une semaine ne participe qu'à un seul bloc. Les semaines finales ne constituant pas un bloc complet ne déclenchent pas d'adaptation.

**Priorités**

```text
sécurité
-> affûtage
-> régénération
-> adaptation
-> progression normale
```

Une adaptation précédant une régénération modifie la charge de référence ; la réduction de régénération est ensuite appliquée.

**Tables lues** : `training_plan`, `session`, `performance`, `algorithm_parameter` via le provider.

### 16.3 `SessionAdjustmentService`

**Responsabilité**

Appliquer la décision aux séances futures.

**Règles**

- Modifier uniquement les séances futures à l'état `planned`.
- Ne jamais modifier les séances terminées ou manquées déjà validées.
- Ne jamais déplacer la date cible.
- Ne jamais modifier `goal_event`.
- Respecter la progression maximale de 10 %.
- Respecter les plafonds qualitatifs et de D+.
- Préserver les règles d'enchaînement du pôle.
- Repasser le calendrier dans `SessionScheduleValidator` avant `flush()`.

**Tables alimentées** : `session`, `session_intensity_zone`.

## 17. Export

### 17.1 `TrainingPlanPdfExporter`

**Responsabilité**

Produire un PDF depuis un plan existant sans recalculer celui-ci.

```php
public function export(TrainingPlan $trainingPlan): string;
```

Le contrôleur transforme la chaîne produite en réponse téléchargeable.

**Tables lues** : `training_plan`, `session`, `session_intensity_zone`, `intensity_zone`.

## 18. Catalogue final `AlgorithmParameter`

Toutes les valeurs restent numériques.

### 18.1 Progression et régénération

| Clé | Valeur |
|---|---:|
| `progression_max_percent` | `10` |
| `long_run_ratio_max` | `0.40` |
| `recovery_week_frequency` | `4` |
| `recovery_load_factor` | `0.70` |

### 18.2 Séances par pôle

| Clé | Valeur |
|---|---:|
| `sessions_discovery` | `2` |
| `sessions_intermediate` | `3` |
| `sessions_performance` | `5` |
| `recovery_sessions_intermediate` | `2` |
| `recovery_sessions_performance` | `4` |

### 18.3 Coefficients VMA

| Clé | Valeur |
|---|---:|
| `coef_recovery` | `0.60` |
| `coef_endurance` | `0.70` |
| `coef_active` | `0.80` |
| `coef_threshold` | `0.88` |
| `coef_vma` | `1.00` |

### 18.4 Répartition par pôle

| Clé | Valeur |
|---|---:|
| `endurance_ratio_discovery` | `0.90` |
| `endurance_ratio_intermediate` | `0.80` |
| `endurance_ratio_performance` | `0.75` |

### 18.5 Bornes de durée

| Clé | Valeur |
|---|---:|
| `plan_min_weeks_discovery` | `8` |
| `plan_min_weeks_intermediate` | `6` |
| `plan_min_weeks_performance` | `4` |
| `plan_max_weeks_discovery` | `24` |
| `plan_max_weeks_intermediate` | `26` |
| `plan_max_weeks_performance` | `24` |

### 18.6 Faisabilité

| Clé | Valeur |
|---|---:|
| `ambitious_duration_ratio_min` | `0.85` |
| `optimistic_duration_ratio_min` | `0.70` |

### 18.7 Validation et adaptation

| Clé | Valeur |
|---|---:|
| `success_validation_rate` | `0.80` |
| `adaptation_window_weeks` | `3` |
| `missed_session_tolerance` | `2` |
| `increase_factor` | `1.05` |
| `decrease_factor` | `0.95` |

### 18.8 Affûtage

| Clé | Valeur |
|---|---:|
| `taper_weeks_intermediate` | `1` |
| `taper_weeks_performance` | `2` |
| `taper_sessions_discovery` | `2` |
| `taper_sessions_intermediate` | `2` |
| `taper_sessions_performance_week_2` | `4` |
| `taper_sessions_performance_week_1` | `3` |
| `taper_load_intermediate` | `0.70` |
| `taper_load_performance_week_2` | `0.80` |
| `taper_load_performance_week_1` | `0.60` |

### 18.9 Dénivelé

| Clé | Valeur |
|---|---:|
| `elevation_equivalent_km_per_100m` | `1.00` |
| `elevation_load_weight` | `0.50` |
| `elevation_load_bonus_max` | `0.50` |
| `elevation_progression_max_percent` | `10` |

Les anciennes clés suivantes sont abandonnées :

```text
max_sessions_discovery
max_sessions_intermediate
max_sessions_performance
default_plan_min_weeks
default_plan_max_weeks
endurance_weight
quality_weight
```

## 19. Contrôleur API

### 19.1 `Controller/Api/TrainingPlanController`

Le contrôleur :

- lit et dénormalise la requête ;
- valide le DTO ;
- vérifie l'utilisateur authentifié ;
- appelle `TrainingPlanGeneratorService` ;
- sérialise le résultat ;
- retourne `201 Created`.

Il ne calcule ni allures, ni zones, ni durée, ni faisabilité, ni séances.

Des contrôleurs séparés pourront gérer :

- la validation d'une performance ;
- le déplacement contrôlé d'une séance ;
- l'adaptation d'un plan ;
- l'export PDF.

## 20. Exceptions métier

```text
TrainingPlanGenerationException
InvalidTrainingGoalException
InsufficientProfileDataException
InsufficientPerformanceDataException
UnrealisticGoalException
InvalidTrainingScheduleException
AlgorithmParameterNotFoundException
TrainingPlanAdaptationException
InvalidPerformanceCompletionRateException
```

## 21. Tests indispensables

Chaque service possède des tests unitaires. Les parcours d'orchestration possèdent des tests d'intégration.

### Calculs

- conversions vitesse/allure ;
- coefficients VMA ;
- calcul avec et sans FCR ;
- bornes des zones ;
- distance-effort et facteur de D+ ;
- plafonnement du bonus de dénivelé.

### Durée et faisabilité

- lundi suivant, y compris une création un lundi ;
- trois types d'objectif ;
- plages par pôle et distance ;
- corrections du temps visé ;
- six indicateurs de faisabilité ;
- données physiologiques insuffisantes.

### Charge et calendrier

- nombres 2, 3 et 5 ;
- régénération toutes les quatre semaines ;
- facteur de régénération à 70 % ;
- ratios d'endurance ;
- sortie longue limitée à 40 % ;
- intervalles Discovery et Intermediate ;
- enchaînements interdits Performance ;
- somme des zones à 100 % ;
- progression du D+ limitée à 10 %.

### Affûtage

- priorité sur la régénération ;
- nombres de séances par pôle ;
- coefficients 70 %, 80 % et 60 % ;
- création unique de `goal_event` à la date cible.

### Performances et adaptation

- conversion 0 à 100 % vers `0.00` à `1.00` ;
- statuts `missed`, `partially_done`, `done` ;
- moyenne hebdomadaire ;
- blocs non chevauchants de trois semaines ;
- seuil de 80 % ;
- tolérance de deux séances manquées ;
- facteurs 1.05 et 0.95 ;
- aucune modification du passé, de l'affûtage ou de `goal_event`.

## 22. Ordre recommandé d'implémentation

1. Enums PHP métier et DTO.
2. Migration ajoutant `target_duration_sec` et `completion_rate`.
3. Valeurs initiales d'`algorithm_parameter`.
4. `AlgorithmParameterProvider` et validation du profil.
5. Calculs purs : dates, allures, zones, D+ et charge.
6. Durée recommandée et faisabilité.
7. Charge hebdomadaire, régénération et affûtage.
8. Génération et validation du calendrier.
9. Orchestration transactionnelle et contrôleur API.
10. Validation des performances.
11. Analyse et adaptation par blocs.
12. Export PDF.

## 23. Vérifications avant livraison

```bash
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/console lint:yaml config
php bin/phpunit
```

Avant toute migration :

```bash
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:diff
```

Une migration destructive ne doit jamais être exécutée automatiquement.
