import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const zoneColors = ["#22d3ee", "#34d399", "#facc15", "#fb923c", "#f43f5e"];

const destroyDistribution = (container) => {
    container.intensityChart?.destroy();
    delete container.intensityChart;
    container.intensityAbortController?.abort();
    delete container.intensityAbortController;
    if (container.intensityFilterHandler) {
        container.querySelector("[data-intensity-period]")?.removeEventListener("change", container.intensityFilterHandler);
        container.querySelector("[data-intensity-scope]")?.removeEventListener("change", container.intensityFilterHandler);
        delete container.intensityFilterHandler;
    }
    delete container.dataset.intensityDistributionInitialized;
};

const legendButton = (zone, color, chart, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "group flex w-full items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-cyan-200/40 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-300/60";
    button.setAttribute("aria-label", `${zone.label || zone.name}, ${zone.sessions} séance${zone.sessions > 1 ? "s" : ""}, ${zone.percentage} %`);

    const identity = document.createElement("span");
    identity.className = "flex items-center gap-3";
    const swatch = document.createElement("span");
    swatch.className = "h-3.5 w-3.5 shrink-0 rounded-full shadow-lg";
    swatch.style.backgroundColor = color;
    const name = document.createElement("span");
    name.className = "font-extrabold text-white";
    name.textContent = zone.label || zone.name;
    identity.append(swatch, name);

    const metrics = document.createElement("span");
    metrics.className = "text-right";
    const percentage = document.createElement("strong");
    percentage.className = "block text-lg font-black text-cyan-200";
    percentage.textContent = `${zone.percentage} %`;
    const sessions = document.createElement("span");
    sessions.className = "block text-xs text-white/50";
    sessions.textContent = `${zone.sessions} séance${zone.sessions > 1 ? "s" : ""}`;
    metrics.append(percentage, sessions);
    button.append(identity, metrics);

    const activate = () => {
        chart.setActiveElements([{ datasetIndex: 0, index }]);
        chart.tooltip.setActiveElements([{ datasetIndex: 0, index }], { x: 0, y: 0 });
        chart.update();
    };
    const deactivate = () => {
        chart.setActiveElements([]);
        chart.tooltip.setActiveElements([], { x: 0, y: 0 });
        chart.update();
    };
    button.addEventListener("mouseenter", activate);
    button.addEventListener("focus", activate);
    button.addEventListener("mouseleave", deactivate);
    button.addEventListener("blur", deactivate);

    return button;
};

const renderDistribution = (container, payload) => {
    const canvas = container.querySelector("[data-intensity-chart]");
    const legend = container.querySelector("[data-intensity-legend]");
    const zones = payload.zones;
    const colors = zones.map((_, index) => zoneColors[index % zoneColors.length]);

    container.intensityChart = new Chart(canvas, {
        type: "pie",
        data: {
            labels: zones.map(({ label, name }) => label || name),
            datasets: [{
                data: zones.map(({ percentage }) => percentage),
                backgroundColor: colors,
                borderColor: "#07132e",
                borderWidth: 4,
                hoverBorderColor: "#e0f2fe",
                hoverOffset: 10,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ({ dataIndex }) => {
                            const zone = zones[dataIndex];
                            return `${zone.label || zone.name} : ${zone.percentage} % (${zone.sessions} séance${zone.sessions > 1 ? "s" : ""})`;
                        },
                    },
                },
            },
        },
    });

    legend.replaceChildren(...zones.map((zone, index) => legendButton(
        zone,
        colors[index],
        container.intensityChart,
        index,
    )));
};

const renderBalance = (container, balance) => {
    const card = container.querySelector("[data-training-balance]");
    const label = container.querySelector("[data-training-balance-label]");
    const message = container.querySelector("[data-training-balance-message]");
    const detail = container.querySelector("[data-training-balance-detail]");
    const balanced = balance.status === "balanced";

    card.className = `mt-4 rounded-2xl border px-5 py-4 ${balanced
        ? "border-emerald-300/30 bg-emerald-300/10"
        : "border-amber-300/30 bg-amber-300/10"}`;
    label.className = `text-xs font-extrabold uppercase tracking-[0.18em] ${balanced ? "text-emerald-200" : "text-amber-200"}`;
    label.textContent = balanced ? "Plan équilibré" : "Point de vigilance";
    message.textContent = balance.message;
    detail.textContent = balance.reference === null ? "" : [
        `Endurance ${balance.actual.endurance} %`,
        `Seuil ${balance.actual.threshold} %`,
        `VMA ${balance.actual.vma} %`,
    ].join(" · ");
    card.hidden = false;
};

const loadDistribution = async (container) => {
    const status = container.querySelector("[data-intensity-status]");
    const content = container.querySelector("[data-intensity-content]");
    const empty = container.querySelector("[data-intensity-empty]");
    const statistics = container.querySelector("[data-intensity-statistics]");
    const balance = container.querySelector("[data-training-balance]");
    const url = new URL(container.dataset.intensityUrl, window.location.origin);
    url.searchParams.set("period", container.querySelector("[data-intensity-period]").value);
    url.searchParams.set("scope", container.querySelector("[data-intensity-scope]").value);
    container.intensityChart?.destroy();
    delete container.intensityChart;
    container.intensityAbortController?.abort();
    container.intensityAbortController = new AbortController();
    content.hidden = true;
    empty.hidden = true;
    statistics.hidden = true;
    balance.hidden = true;
    status.hidden = false;
    status.textContent = "Actualisation de vos zones d’intensité…";
    status.classList.remove("text-amber-200");

    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            signal: container.intensityAbortController.signal,
        });

        if (!response.ok) throw new Error("Intensity distribution request failed");

        const payload = await response.json();
        status.hidden = true;

        if (!Array.isArray(payload.zones) || payload.zones.length === 0) {
            empty.hidden = false;
            return;
        }

        container.querySelector("[data-intensity-total]").textContent = payload.total_sessions;
        container.querySelector("[data-intensity-dominant]").textContent = payload.dominant_zone || "—";
        renderDistribution(container, payload);
        renderBalance(container, payload.balance);
        content.hidden = false;
        statistics.hidden = false;
    } catch (error) {
        if (error.name === "AbortError") return;

        status.textContent = "Impossible de charger la répartition des zones pour le moment.";
        status.classList.add("text-amber-200");
    }
};

const initializeDistribution = (container) => {
    if (container.dataset.intensityDistributionInitialized === "true") return;
    container.dataset.intensityDistributionInitialized = "true";
    container.intensityFilterHandler = () => loadDistribution(container);
    container.querySelector("[data-intensity-period]").addEventListener("change", container.intensityFilterHandler);
    container.querySelector("[data-intensity-scope]").addEventListener("change", container.intensityFilterHandler);
    loadDistribution(container);
};

const initializeAllDistributions = () => {
    document.querySelectorAll("[data-intensity-distribution]").forEach(initializeDistribution);
};

document.addEventListener("DOMContentLoaded", initializeAllDistributions);
document.addEventListener("turbo:load", initializeAllDistributions);
document.addEventListener("turbo:before-cache", () => {
    document.querySelectorAll("[data-intensity-distribution]").forEach(destroyDistribution);
});

initializeAllDistributions();
