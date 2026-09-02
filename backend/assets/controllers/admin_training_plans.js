const initializeAdminTrainingPlans = (root) => {
    if (root.dataset.adminTrainingPlansInitialized === "true") return;
    root.dataset.adminTrainingPlansInitialized = "true";

    const status = root.querySelector("[data-admin-training-plans-status]");
    const table = root.querySelector("[data-admin-training-plans-table]");
    const body = root.querySelector("[data-admin-training-plans-body]");
    const empty = root.querySelector("[data-admin-training-plans-empty]");
    const pagination = root.querySelector("[data-admin-training-plans-pagination]");
    const previous = root.querySelector("[data-admin-training-plans-previous]");
    const next = root.querySelector("[data-admin-training-plans-next]");
    const pageLabel = root.querySelector("[data-admin-training-plans-page]");
    const total = root.querySelector("[data-admin-training-plans-total]");
    const totalPlural = root.querySelector("[data-admin-training-plans-total-plural]");
    const filters = root.querySelector("[data-admin-training-plans-filters]");
    const userFilter = root.querySelector("[data-admin-training-plans-user]");
    const feasibilityFilter = root.querySelector("[data-admin-training-plans-feasibility]");
    const statusFilter = root.querySelector("[data-admin-training-plans-status-filter]");
    let currentPage = 1;

    const cell = (text, className = "") => {
        const element = document.createElement("td");
        element.className = ("px-4 py-4 align-top " + className).trim();
        element.textContent = text;
        return element;
    };

    const objective = (plan) => {
        const target = String(plan.target_value) + " " + plan.target_unit;
        const duration = plan.target_duration_minutes
            ? " en " + String(plan.target_duration_minutes) + " min"
            : "";

        return target + duration + " · " + plan.terrain_type;
    };

    const feasibilityCell = (plan) => {
        const element = document.createElement("td");
        element.className = "px-4 py-4 align-top";
        const badge = document.createElement("span");
        const styles = {
            OPTIMAL: "border-emerald-300/30 bg-emerald-300/10 text-emerald-200",
            ACCEPTABLE: "border-orange-300/30 bg-orange-300/10 text-orange-200",
            IMPOSSIBLE: "border-red-300/30 bg-red-300/10 text-red-200",
            INCONNU: "border-white/20 bg-white/5 text-white/60",
        };
        const statusValue = plan.feasibility_status || "INCONNU";
        const dot = document.createElement("span");
        dot.className = "h-2 w-2 rounded-full bg-current";
        dot.setAttribute("aria-hidden", "true");
        badge.className = "inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-black tracking-wide " +
            (styles[statusValue] || styles.INCONNU);
        badge.dataset.feasibilityStatus = statusValue;
        badge.setAttribute("aria-label", "Faisabilité : " + statusValue);
        badge.append(dot, document.createTextNode(statusValue));
        element.append(badge);

        return element;
    };

    const progressCell = (plan) => {
        const rawScore = Number(plan.progress_score);
        const score = Number.isFinite(rawScore) ? Math.min(100, Math.max(0, rawScore)) : 0;
        const level = score < 30 ? "LOW" : score < 70 ? "MEDIUM" : "HIGH";
        const styles = {
            LOW: {
                text: "text-red-200",
                track: "bg-red-950/70",
                bar: "bg-red-400",
            },
            MEDIUM: {
                text: "text-orange-200",
                track: "bg-orange-950/70",
                bar: "bg-orange-400",
            },
            HIGH: {
                text: "text-cyan-100",
                track: "bg-cyan-950/70",
                bar: "bg-cyan-300",
            },
        };
        const element = document.createElement("td");
        element.className = "min-w-36 px-4 py-4 align-top";
        const value = document.createElement("span");
        value.className = "text-sm font-black " + styles[level].text;
        value.textContent = Math.round(score) + " %";
        const track = document.createElement("span");
        track.className = "progress-bar mt-2 block h-2 overflow-hidden rounded-full " + styles[level].track;
        track.dataset.progressLevel = level.toLowerCase();
        track.setAttribute("role", "progressbar");
        track.setAttribute("aria-label", "Score de progression");
        track.setAttribute("aria-valuemin", "0");
        track.setAttribute("aria-valuemax", "100");
        track.setAttribute("aria-valuenow", String(Math.round(score)));
        const bar = document.createElement("span");
        bar.className = "block h-full rounded-full transition-[width] duration-500 " + styles[level].bar;
        bar.style.width = score + "%";
        track.append(bar);
        element.append(value, track);

        return element;
    };

    const planNameCell = (plan) => {
        const element = cell(plan.name, "font-semibold text-white");
        if (!plan.is_problematic) {
            return element;
        }

        const warning = document.createElement("span");
        warning.className = "mt-2 inline-flex rounded-full border border-red-300/30 bg-red-300/10 px-2 py-1 text-xs font-black text-red-200";
        warning.textContent = "⚠ " + plan.anomaly_count + " anomalie" + (plan.anomaly_count > 1 ? "s" : "");
        warning.setAttribute("aria-label", plan.anomaly_count + " anomalies détectées");
        element.append(document.createElement("br"), warning);

        return element;
    };

    const render = (plans) => {
        const statusLabels = {
            ACTIVE: "Actif",
            COMPLETED: "Terminé",
            ARCHIVED: "Archivé",
        };
        body.replaceChildren();
        plans.forEach((plan) => {
            const row = document.createElement("tr");
            row.className = "transition hover:bg-cyan-300/5";
            if (plan.is_problematic) {
                row.classList.add("bg-red-400/5");
                row.dataset.problematicPlan = "true";
            }
            row.append(
                cell(plan.user || plan.user_email, "font-bold text-cyan-100"),
                planNameCell(plan),
                cell(objective(plan), "text-white/65"),
                feasibilityCell(plan),
                progressCell(plan),
                cell(plan.current_week + " / " + plan.duration_weeks, "text-white/65"),
                cell(plan.duration_weeks + " semaines", "text-white/65"),
                cell(statusLabels[plan.status] || plan.status, "font-bold text-white/80"),
            );
            body.append(row);
        });
    };

    const load = async (page = 1) => {
        status.hidden = false;
        status.textContent = "Chargement des plans…";
        status.classList.remove("border-red-400/30", "bg-red-400/10", "text-red-100");
        table.hidden = true;
        empty.hidden = true;
        pagination.hidden = true;

        try {
            const url = new URL(root.dataset.adminTrainingPlansUrl, window.location.origin);
            url.searchParams.set("page", String(page));
            url.searchParams.set("perPage", "20");
            url.searchParams.set("feasibility", feasibilityFilter.value);
            url.searchParams.set("user", userFilter.value.trim());
            url.searchParams.set("status", statusFilter.value);
            const response = await fetch(url, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || "Impossible de charger les plans.");
            }

            currentPage = data.pagination.page;
            total.textContent = String(data.pagination.total);
            totalPlural.textContent = data.pagination.total > 1 ? "s" : "";
            status.hidden = true;

            if (data.items.length === 0) {
                empty.hidden = false;
                return;
            }

            render(data.items);
            table.hidden = false;
            pagination.hidden = data.pagination.totalPages <= 1;
            pageLabel.textContent = "Page " + currentPage + " sur " + data.pagination.totalPages;
            previous.disabled = currentPage <= 1;
            next.disabled = currentPage >= data.pagination.totalPages;
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : "Une erreur est survenue.";
            status.classList.add("border-red-400/30", "bg-red-400/10", "text-red-100");
        }
    };

    root.adminTrainingPlansPreviousHandler = () => load(currentPage - 1);
    root.adminTrainingPlansNextHandler = () => load(currentPage + 1);
    root.adminTrainingPlansSubmitHandler = (event) => {
        event.preventDefault();
        load(1);
    };
    root.adminTrainingPlansFilterHandler = () => load(1);
    root.adminTrainingPlansResetHandler = () => window.setTimeout(() => load(1), 0);

    previous.addEventListener("click", root.adminTrainingPlansPreviousHandler);
    next.addEventListener("click", root.adminTrainingPlansNextHandler);
    filters.addEventListener("submit", root.adminTrainingPlansSubmitHandler);
    feasibilityFilter.addEventListener("change", root.adminTrainingPlansFilterHandler);
    statusFilter.addEventListener("change", root.adminTrainingPlansFilterHandler);
    filters.addEventListener("reset", root.adminTrainingPlansResetHandler);
    load();
};

const destroyAdminTrainingPlans = (root) => {
    root.querySelector("[data-admin-training-plans-previous]")
        ?.removeEventListener("click", root.adminTrainingPlansPreviousHandler);
    root.querySelector("[data-admin-training-plans-next]")
        ?.removeEventListener("click", root.adminTrainingPlansNextHandler);
    root.querySelector("[data-admin-training-plans-filters]")
        ?.removeEventListener("submit", root.adminTrainingPlansSubmitHandler);
    root.querySelector("[data-admin-training-plans-feasibility]")
        ?.removeEventListener("change", root.adminTrainingPlansFilterHandler);
    root.querySelector("[data-admin-training-plans-status-filter]")
        ?.removeEventListener("change", root.adminTrainingPlansFilterHandler);
    root.querySelector("[data-admin-training-plans-filters]")
        ?.removeEventListener("reset", root.adminTrainingPlansResetHandler);
    delete root.dataset.adminTrainingPlansInitialized;
};

const initializeAllAdminTrainingPlans = () => document
    .querySelectorAll("[data-admin-training-plans]")
    .forEach(initializeAdminTrainingPlans);

document.addEventListener("DOMContentLoaded", initializeAllAdminTrainingPlans);
document.addEventListener("turbo:load", initializeAllAdminTrainingPlans);
document.addEventListener("turbo:before-cache", () => document
    .querySelectorAll("[data-admin-training-plans]")
    .forEach(destroyAdminTrainingPlans));

initializeAllAdminTrainingPlans();
