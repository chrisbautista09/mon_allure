# Météo du coureur — besoins fonctionnels

Ce document définit le contrat fonctionnel de l’US 4.4 avant le choix d’un
fournisseur météo. L’objectif est d’aider le coureur à préparer sa séance, pas
de reproduire une application météorologique complète.

## Périmètre

Le dashboard affiche les conditions actuelles et la prévision utile pour la
prochaine séance planifiée. Les données doivent correspondre à la position
retenue pour l’utilisateur et à son fuseau horaire local.

## Données obligatoires

| Donnée | Unité/format | Utilité pour la course |
| --- | --- | --- |
| Température | °C, entier affiché | Choix de la tenue et risque thermique |
| Température ressentie | °C, entier affiché | Effet combiné du vent et de l’humidité |
| Humidité relative | %, entier | Difficulté de thermorégulation |
| Vent moyen | km/h, entier | Effort et allure ressentie |
| Rafales | km/h, entier, nullable | Sécurité lorsque le vent est irrégulier |
| Direction du vent | point cardinal, nullable | Anticipation du parcours exposé |
| Conditions | code stable + libellé français | Compréhension immédiate et icône |
| Probabilité de pluie | %, entier | Choix de l’équipement et du parcours |
| Précipitations | mm, nullable | Intensité réellement attendue |
| Heure de prévision | ISO 8601 avec fuseau | Contrôle de la fraîcheur de la donnée |

L’indice UV est facultatif. Il est affiché uniquement lorsque le fournisseur le
retourne, principalement pour les séances en journée.

Pression atmosphérique, visibilité, point de rosée, phases lunaires et données
marines sont exclus du premier affichage : ils n’aident pas directement à la
décision d’entraînement attendue par le projet.

## Fréquence et fraîcheur

- Les données sont mises en cache pendant une heure par position.
- Une nouvelle consultation après expiration déclenche une actualisation.
- La date de dernière mise à jour reste visible.
- Une réponse en cache peut être servie pendant une panne temporaire du
  fournisseur si elle a moins de trois heures ; elle doit alors être signalée
  comme donnée ancienne (`STALE`).

## Localisation

Le contrat utilise des coordonnées décimales `latitude` et `longitude`. Leur
mode d’obtention sera traité séparément : localisation autorisée par le
navigateur, position enregistrée ou ville de repli. Aucune adresse précise ne
doit être transmise au fournisseur.

## Format du dashboard

Affichage principal, lisible en quelques secondes :

```text
Météo aujourd’hui · Lyon
18 °C · ressenti 17 °C · Nuageux
Humidité 65 % · Vent 15 km/h · Pluie 20 %
Mis à jour à 08:00
```

La température et la condition dominent visuellement. Les autres valeurs sont
présentées en informations secondaires. Le composant doit être utilisable au
clavier, ne pas dépendre uniquement des couleurs et fournir des libellés aux
icônes.

## Contrat normalisé attendu

Le service applicatif devra retourner un objet indépendant du fournisseur :

```json
{
  "location": "Lyon",
  "latitude": 45.76,
  "longitude": 4.84,
  "temperatureC": 18.2,
  "feelsLikeC": 17.4,
  "humidityPercent": 65,
  "windSpeedKmh": 15.0,
  "windGustKmh": 24.0,
  "windDirection": "NE",
  "conditionCode": "CLOUDY",
  "conditionLabel": "Nuageux",
  "rainProbabilityPercent": 20,
  "precipitationMm": 0.2,
  "uvIndex": 3.1,
  "forecastAt": "2026-08-26T09:00:00+02:00",
  "updatedAt": "2026-08-26T08:05:00+02:00",
  "freshness": "FRESH"
}
```

## États particuliers

- `LOCATION_REQUIRED` : aucune position exploitable ; inviter l’utilisateur à
  autoriser la localisation ou choisir une ville.
- `UNAVAILABLE` : fournisseur indisponible et aucun cache utilisable ; afficher
  un message neutre sans bloquer le dashboard.
- `STALE` : dernière donnée connue de moins de trois heures ; afficher son heure
  et signaler qu’elle n’est pas actualisée.
- Valeur facultative absente : masquer uniquement la ligne concernée, jamais la
  carte entière.

## Critères de choix du fournisseur

Open-Meteo Forecast API est retenue à la sub-issue #99 : elle propose les
données obligatoires, des prévisions horaires, des coordonnées géographiques,
des unités métriques et une documentation stable sans clé ni carte bancaire.
Son attribution CC BY 4.0 devra être visible dans l’interface.
