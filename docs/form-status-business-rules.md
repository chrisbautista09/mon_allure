# Indicateur d’état de forme — règles métier

Ce document définit la règle fonctionnelle de l’US 4.3 « État de forme
actuel ». Il constitue le contrat des futures implémentations du service, de
l’API et du dashboard.

## Données utilisées

Le calcul concerne uniquement le plan actif de l’utilisateur authentifié et
utilise :

- son `TrainingPlan.progressScore`, borné entre 0 et 100 ;
- au maximum ses 10 dernières performances, puis uniquement celles rattachées
  à ce plan et réalisées durant les 21 jours qui précèdent la date du calcul ;
- pour chaque performance exploitable, la distance et la durée réalisées,
  comparées à la distance et à la durée prévues de la séance.

Une performance dont les valeurs prévues ou réalisées sont absentes, nulles
ou négatives n’entre pas dans le calcul récent. Les performances d’un autre
plan sont toujours ignorées.

## Score récent

Pour chaque performance exploitable :

1. `distanceRatio = distanceKm / plannedDistanceKm` ;
2. `paceRatio = actualSpeed / plannedSpeed` ;
3. `performanceScore = min(distanceRatio, paceRatio, 1) × 100`.

Le score récent est une moyenne pondérée : les performances sont classées de
la plus récente à la plus ancienne et reçoivent des poids linéaires allant de
`n` à `1`. La séance la plus récente a ainsi davantage d’impact. Limiter chaque
ratio à 1 évite qu’une séance très supérieure masque plusieurs séances
incomplètes.

## Score de forme

Lorsque des performances récentes sont disponibles :

```text
formScore = (progressScore × 0,30) + (recentScore × 0,70)
```

Le résultat est borné entre 0 et 100 et arrondi à l’entier le plus proche pour
l’affichage.

Pour un nouvel utilisateur qui ne possède encore aucune performance, aucun
score n’est affiché : l’état reste indisponible jusqu’à sa première séance
réalisée. Si des performances existent mais qu’aucune n’appartient à la fenêtre
récente, le `progressScore` devient le score de forme. En l’absence de plan
actif ou terminé, aucun score n’est calculé.

## Niveau affiché

| Score | Code | Libellé utilisateur |
| ---: | --- | --- |
| 0 à 39 | `LOW` | Forme faible |
| 40 à 69 | `FAIR` | Forme correcte |
| 70 à 89 | `GOOD` | Bonne forme |
| 90 à 100 | `EXCELLENT` | Très bonne forme |

Les bornes sont inclusives et ne se chevauchent pas.

## Tendance

La tendance compare la moyenne des trois performances exploitables les plus
récentes à celle des trois performances précédentes :

- écart supérieur ou égal à +5 points : `IMPROVING` (`progression`) ;
- écart inférieur ou égal à -5 points : `DECLINING` (`baisse`) ;
- écart compris entre -5 et +5 points : `STABLE` (`stable`).

Si l’une des deux périodes ne contient aucune performance exploitable, la
tendance est `UNKNOWN` (`données insuffisantes`).

## États particuliers

- Aucun plan actif ou terminé : `NO_ACTIVE_PLAN`, score à `null` et message
  invitant à définir un objectif.
- Plan actif sans aucune performance : `NO_PERFORMANCE`, score à `null` et
  invitation à réaliser les premières séances.
- Moins de six performances récentes : `LIMITED_DATA` et message « Analyse en
  cours ».
- Six performances récentes ou plus : `READY`.
- Plan terminé : `PLAN_COMPLETED`, dernier niveau atteint conservé.
- Valeurs aberrantes : ratios et score final bornés entre 0 et 100.
- Le calcul est déterministe : les mêmes données à la même date produisent le
  même résultat.

## Contrat de sortie attendu

```json
{
  "score": 78,
  "status": "GOOD",
  "label": "Bonne forme",
  "trend": "IMPROVING",
  "trendLabel": "progression",
  "trendMessage": "Votre forme progresse.",
  "recentPerformanceCount": 4,
  "calculatedAt": "2026-08-26T09:00:00+02:00"
}
```

L’API devra exposer les codes stables pour le traitement côté interface et les
libellés français pour un affichage immédiatement compréhensible.
