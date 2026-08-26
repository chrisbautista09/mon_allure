# Décision fournisseur météo

Date de décision : 26 août 2026
US concernée : #97 — Weather Forecast
Sub-issue : #99 — Select external weather API

## Décision

Mon Allure utilisera **Open-Meteo Forecast API** comme fournisseur météo.

Endpoint cible :

```text
https://api.open-meteo.com/v1/forecast
```

L’offre gratuite non commerciale ne nécessite **ni compte, ni clé API, ni
carte bancaire**. Elle est adaptée au cadre pédagogique et à la soutenance du
projet Mon Allure.

## Comparaison

| Fournisseur | Points forts | Limites pour Mon Allure | Décision |
| --- | --- | --- | --- |
| Open-Meteo | Sans inscription, clé ou carte ; jusqu’à 10 000 appels/jour en usage non commercial ; données actuelles et horaires ; modèles météo européens dont Météo-France ; API JSON documentée | Attribution obligatoire ; aucune garantie de disponibilité sur l’offre gratuite | Retenu |
| OpenWeather One Call | Données actuelles et prévisions réunies, documentation riche, traduction française | Souscription séparée et carte bancaire obligatoires pour One Call, malgré le quota gratuit | Non retenu |
| WeatherAPI | Documentation simple, météo temps réel et horaire, quota gratuit annoncé de 100 000 appels/mois | Compte et clé nécessaires ; lien d’attribution demandé sur l’offre gratuite | Alternative |
| Meteostat | Très adapté aux observations et historiques météorologiques | Moins adapté à la prévision opérationnelle de la prochaine séance | Non retenu |

Open-Meteo couvre les besoins obligatoires définis dans
[`weather-functional-requirements.md`](weather-functional-requirements.md) :
température, température ressentie, humidité, vent, rafales, direction, code
météo, probabilité et quantité de pluie ainsi qu’indice UV.

## Paramètres prévus

```text
latitude={latitude}
longitude={longitude}
current=temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,wind_gusts_10m,precipitation
hourly=temperature_2m,apparent_temperature,relative_humidity_2m,precipitation_probability,precipitation,weather_code,wind_speed_10m,wind_direction_10m,wind_gusts_10m,uv_index
temperature_unit=celsius
wind_speed_unit=kmh
precipitation_unit=mm
timezone=auto
forecast_days=3
```

Le service Symfony normalisera les noms propres à Open-Meteo vers le contrat
applicatif défini pour le dashboard.

## Quota et cache

L’offre gratuite non commerciale annonce les limites suivantes :

- 600 appels par minute ;
- 5 000 appels par heure ;
- 10 000 appels par jour ;
- 300 000 appels par mois.

Avec un cache d’une heure par coordonnées arrondies, une position consultée en
continu représente au maximum 24 appels quotidiens. Le besoin du projet reste
donc très inférieur à la limite gratuite.

## Attribution obligatoire

Les données sont fournies sous licence CC BY 4.0. Le futur composant dashboard
devra afficher un lien visible, par exemple :

```text
Données météo : Open-Meteo
```

Le lien pointera vers `https://open-meteo.com/`. La documentation du projet et
la page concernée conserveront cette attribution.

## Sécurité et confidentialité

- Aucun secret météo n’est nécessaire dans `.env` ou `.env.local`.
- Seules latitude et longitude sont transmises ; aucune adresse personnelle.
- L’appel est effectué par le backend Symfony pour centraliser cache, gestion
  des erreurs et normalisation.
- La position utilisée dans la clé de cache est arrondie afin d’éviter une
  précision inutile et de mutualiser les requêtes proches.

## Évolution future

L’offre gratuite Open-Meteo est réservée à un usage non commercial. Si Mon
Allure devient un service commercial, il faudra souscrire une offre commerciale
ou réévaluer le fournisseur avant mise en production. Le contrat applicatif
normalisé permettra ce remplacement sans modifier l’interface.

## Documentation officielle

- [Open-Meteo Forecast API](https://open-meteo.com/en/docs)
- [Tarifs et limites Open-Meteo](https://open-meteo.com/en/pricing)
- [Conditions d’utilisation Open-Meteo](https://open-meteo.com/en/terms)
