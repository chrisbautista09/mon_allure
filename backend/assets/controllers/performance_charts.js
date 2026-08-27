import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const formatDate = (date) => new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "2-digit",
}).format(new Date(`${date}T00:00:00`));

const formatDuration = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return hours > 0 ? `${hours} h ${minutes.toString().padStart(2, "0")} min` : `${minutes} min`;
};

const chartOptions = (unit, color, valueFormatter = (value) => `${value} ${unit}`) => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: ({ parsed }) => valueFormatter(parsed.y),
            },
        },
    },
    scales: {
        x: {
            grid: { color: "rgba(148, 163, 184, 0.08)" },
            ticks: { color: "rgba(255, 255, 255, 0.62)", maxRotation: 0, autoSkip: true },
        },
        y: {
            beginAtZero: true,
            grid: { color: "rgba(148, 163, 184, 0.12)" },
            ticks: {
                color: "rgba(255, 255, 255, 0.62)",
                callback: (value) => valueFormatter(value),
            },
        },
    },
    elements: {
        line: { borderWidth: 2.5, tension: 0.3 },
        point: { radius: 3, hoverRadius: 6, backgroundColor: color },
    },
});

const comparisonColors = {
    attained: "#34d399",
    close: "#fb923c",
    not_attained: "#fb7185",
    unavailable: "#94a3b8",
};

const createChart = (canvas, label, labels, values, plannedValues, statuses, color, options) => new Chart(canvas, {
    type: "line",
    data: {
        labels,
        datasets: [
            {
                label: `${label} réalisée`,
                data: values,
                borderColor: color,
                backgroundColor: `${color}26`,
                pointBackgroundColor: statuses.map((status) => comparisonColors[status] || comparisonColors.unavailable),
                pointBorderColor: statuses.map((status) => comparisonColors[status] || comparisonColors.unavailable),
                fill: true,
            },
            {
                label: `${label} prévue`,
                data: plannedValues,
                borderColor: "rgba(255, 255, 255, 0.55)",
                backgroundColor: "transparent",
                borderDash: [6, 5],
                pointRadius: 2,
                fill: false,
            },
        ],
    },
    options,
});

const destroyChartInstances = (container) => {
    (container.performanceCharts || []).forEach((chart) => chart.destroy());
    container.performanceCharts = [];
};

const destroyCharts = (container) => {
    destroyChartInstances(container);
    container.performanceAbortController?.abort();
    delete container.performanceAbortController;

    if (container.performancePeriodHandler) {
        container.querySelector("[data-performance-period]")?.removeEventListener("change", container.performancePeriodHandler);
        delete container.performancePeriodHandler;
    }

    delete container.dataset.performanceChartsInitialized;
};

const updateSummary = (container, history) => {
    const count = history.length;
    const totalDistance = history.reduce((total, item) => total + item.distance, 0);
    const totalTime = history.reduce((total, item) => total + item.time, 0);
    const totalElevation = history.reduce((total, item) => total + (item.elevation || 0), 0);
    const value = (name) => container.querySelector(`[data-performance-summary="${name}"]`);

    value("totalDistance").textContent = `${Math.round(totalDistance * 100) / 100} km`;
    value("totalTime").textContent = formatDuration(totalTime);
    value("totalElevation").textContent = `${totalElevation} m`;
    value("sessionCount").textContent = count.toString();
    value("averageDistance").textContent = `${count > 0 ? Math.round((totalDistance / count) * 100) / 100 : 0} km`;
    value("averageTime").textContent = formatDuration(count > 0 ? Math.round(totalTime / count) : 0);
};

const loadPerformanceCharts = async (container, period) => {
    const status = container.querySelector("[data-performance-chart-status]");
    const grid = container.querySelector("[data-performance-chart-grid]");
    const emptyState = container.querySelector("[data-performance-empty-state]");
    const elevationContainer = container.querySelector("[data-elevation-chart-container]");
    const elevationEmptyState = container.querySelector("[data-elevation-empty-state]");
    const url = new URL(container.dataset.performanceUrl, window.location.origin);
    url.searchParams.set("period", period);
    container.performanceAbortController?.abort();
    container.performanceAbortController = new AbortController();
    destroyChartInstances(container);
    grid.hidden = true;
    emptyState.hidden = true;
    elevationContainer.hidden = false;
    elevationEmptyState.hidden = true;
    status.hidden = false;
    status.textContent = "Actualisation de vos performances…";
    status.classList.remove("text-amber-200");

    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            signal: container.performanceAbortController.signal,
        });

        if (!response.ok) throw new Error("Performance history request failed");

        const history = await response.json();
        updateSummary(container, Array.isArray(history) ? history : []);

        if (!Array.isArray(history) || history.length === 0) {
            status.hidden = true;
            emptyState.hidden = false;
            return;
        }

        const labels = history.map(({ date }) => formatDate(date));
        const distanceCanvas = container.querySelector('[data-performance-chart="distance"]');
        const timeCanvas = container.querySelector('[data-performance-chart="time"]');
        const elevationCanvas = container.querySelector('[data-performance-chart="elevation"]');
        const hasElevation = history.some(({ elevation }) => elevation !== null);
        const elevationChart = hasElevation
            ? createChart(
                elevationCanvas,
                "Dénivelé",
                labels,
                history.map(({ elevation }) => elevation),
                history.map(({ planned }) => planned.elevation),
                history.map(({ comparison }) => comparison.elevation.status),
                "#6ee7b7",
                chartOptions("m", "#6ee7b7"),
            )
            : null;

        elevationContainer.hidden = !hasElevation;
        elevationEmptyState.hidden = hasElevation;
        container.performanceCharts = [
            createChart(
                distanceCanvas,
                "Distance",
                labels,
                history.map(({ distance }) => distance),
                history.map(({ planned }) => planned.distance),
                history.map(({ comparison }) => comparison.distance.status),
                "#22d3ee",
                chartOptions("km", "#22d3ee"),
            ),
            createChart(
                timeCanvas,
                "Temps",
                labels,
                history.map(({ time }) => time),
                history.map(({ planned }) => planned.time),
                history.map(({ comparison }) => comparison.time.status),
                "#c4b5fd",
                chartOptions("", "#c4b5fd", formatDuration),
            ),
            elevationChart,
        ].filter(Boolean);

        status.textContent = `${history.length} performance${history.length > 1 ? "s" : ""} affichée${history.length > 1 ? "s" : ""}.`;
        grid.hidden = false;
    } catch (error) {
        if (error.name === "AbortError") return;

        emptyState.hidden = true;
        status.textContent = "Impossible de charger vos graphiques pour le moment.";
        status.classList.add("text-amber-200");
    }
};

const initializePerformanceCharts = (container) => {
    if (container.dataset.performanceChartsInitialized === "true") return;
    container.dataset.performanceChartsInitialized = "true";

    const periodSelect = container.querySelector("[data-performance-period]");
    container.performancePeriodHandler = () => loadPerformanceCharts(container, periodSelect.value);
    periodSelect.addEventListener("change", container.performancePeriodHandler);
    loadPerformanceCharts(container, periodSelect.value);
};

const initializeAllPerformanceCharts = () => {
    document.querySelectorAll("[data-performance-charts]").forEach(initializePerformanceCharts);
};

document.addEventListener("DOMContentLoaded", initializeAllPerformanceCharts);
document.addEventListener("turbo:load", initializeAllPerformanceCharts);
document.addEventListener("turbo:before-cache", () => {
    document.querySelectorAll("[data-performance-charts]").forEach(destroyCharts);
});

initializeAllPerformanceCharts();
