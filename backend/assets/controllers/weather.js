const weatherIcon = (condition) => {
    if (condition.includes("Orage")) return "⛈️";
    if (condition.includes("Neige")) return "🌨️";
    if (condition.includes("Pluie") || condition.includes("Bruine")) return "🌧️";
    if (condition.includes("Brouillard")) return "🌫️";
    if (condition.includes("Partiellement")) return "⛅";
    if (condition.includes("Nuageux")) return "☁️";
    if (condition.includes("dégagé")) return "☀️";

    return "🌤️";
};

const initializeWeatherCard = (card) => {
    if (card.dataset.weatherInitialized === "true") return;
    card.dataset.weatherInitialized = "true";

    const element = (role) => card.querySelector(`[data-weather-role="${role}"]`);
    const status = element("status");
    const retry = element("retry");

    const showError = (message) => {
        status.innerHTML = `<p class="text-sm font-semibold text-amber-200">${message}</p>`;
        retry.hidden = false;
        element("result").hidden = true;
        element("details").hidden = true;
    };

    const loadWeather = () => {
        retry.hidden = true;
        status.innerHTML = '<p class="text-sm text-white/70">Localisation et météo en cours de chargement…</p>';

        if (!("geolocation" in navigator)) {
            showError("La géolocalisation n’est pas disponible dans ce navigateur.");
            return;
        }

        navigator.geolocation.getCurrentPosition(async ({ coords }) => {
            const url = new URL(card.dataset.weatherUrl, window.location.origin);
            url.searchParams.set("latitude", coords.latitude.toString());
            url.searchParams.set("longitude", coords.longitude.toString());
            if (card.dataset.weatherLocation) url.searchParams.set("location", card.dataset.weatherLocation);

            try {
                const response = await fetch(url, {
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                });
                const data = await response.json();

                if (!response.ok) {
                    showError(data.message || "La météo est temporairement indisponible.");
                    return;
                }

                element("location").textContent = data.location;
                element("temperature").textContent = Math.round(data.temperature).toString();
                element("condition").textContent = data.condition;
                element("wind").textContent = Math.round(data.wind).toString();
                element("icon").textContent = weatherIcon(data.condition);
                element("icon").setAttribute("aria-label", data.condition);
                element("advice").textContent = data.advice;
                element("result").hidden = false;
                element("details").hidden = false;
                status.innerHTML = data.freshness === "STALE"
                    ? '<p class="text-sm font-semibold text-amber-200">Dernière météo connue : l’actualisation est temporairement indisponible.</p>'
                    : '<p class="text-sm font-semibold text-emerald-200">Météo actualisée pour votre position.</p>';
            } catch {
                showError("Impossible de charger la météo. Votre programme reste disponible.");
            }
        }, () => {
            showError("Autorisez la localisation pour afficher la météo de votre entraînement.");
        }, {
            enableHighAccuracy: false,
            timeout: 8000,
            maximumAge: 3600000,
        });
    };

    retry.addEventListener("click", loadWeather);
    loadWeather();
};

const initializeWeatherCards = () => {
    document.querySelectorAll("[data-weather-card]").forEach(initializeWeatherCard);
};

document.addEventListener("DOMContentLoaded", initializeWeatherCards);
document.addEventListener("turbo:load", initializeWeatherCards);

initializeWeatherCards();
