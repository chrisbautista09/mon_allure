const inputs = (container) => [...container.querySelectorAll("[data-algorithm-parameter-key]")];

const values = (container) => Object.fromEntries(inputs(container).map(input => [
    input.dataset.algorithmParameterKey,
    Number(input.value),
]));

const showStatus = (container, message, type = "neutral") => {
    const status = container.querySelector("[data-admin-algorithm-parameters-status]");
    const styles = {
        neutral: "border-white/10 bg-white/5 text-white/65",
        success: "border-emerald-300/30 bg-emerald-300/10 text-emerald-100",
        error: "border-rose-300/30 bg-rose-300/10 text-rose-100",
    };
    status.className = `mt-6 rounded-xl border px-4 py-3 text-sm font-bold ${styles[type]}`;
    status.textContent = message;
    status.hidden = false;
};

const updateDirtyState = (container) => {
    const dirty = JSON.stringify(values(container)) !== container.adminAlgorithmParametersSnapshot;
    const form = container.querySelector("[data-admin-algorithm-parameters-form]");
    container.querySelector("[data-admin-algorithm-parameters-save]").disabled = !dirty || !form.checkValidity();
};

const loadAlgorithmParameters = async (container) => {
    const status = container.querySelector("[data-admin-algorithm-parameters-status]");
    const form = container.querySelector("[data-admin-algorithm-parameters-form]");

    try {
        const response = await fetch(container.dataset.adminAlgorithmParametersUrl, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        if (!response.ok) throw new Error("Algorithm parameters request failed");

        const payload = await response.json();
        container.dataset.adminAlgorithmParametersCsrf = payload.csrfToken;
        inputs(container).forEach((input) => {
            const value = payload.parameters?.[input.dataset.algorithmParameterKey];
            if (typeof value === "number") input.value = value;
        });
        container.adminAlgorithmParametersSnapshot = JSON.stringify(values(container));
        status.hidden = true;
        form.hidden = false;
    } catch {
        status.textContent = "Impossible de charger les paramètres de l’algorithme pour le moment.";
        status.classList.add("border-amber-300/30", "bg-amber-300/10", "text-amber-100");
    }
};

const saveAlgorithmParameters = async (container, event) => {
    event.preventDefault();
    const form = container.querySelector("[data-admin-algorithm-parameters-form]");
    if (!form.reportValidity()) return;

    const saveButton = container.querySelector("[data-admin-algorithm-parameters-save]");
    saveButton.disabled = true;
    saveButton.setAttribute("aria-busy", "true");
    saveButton.textContent = "Enregistrement…";

    try {
        const response = await fetch(container.dataset.adminAlgorithmParametersUrl, {
            method: "PUT",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": container.dataset.adminAlgorithmParametersCsrf,
            },
            body: JSON.stringify({ parameters: values(container) }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || "L’enregistrement des paramètres a échoué.");

        inputs(container).forEach((input) => {
            const value = payload.parameters?.[input.dataset.algorithmParameterKey];
            if (typeof value === "number") input.value = value;
        });
        container.adminAlgorithmParametersSnapshot = JSON.stringify(values(container));
        showStatus(container, "Les paramètres ont été enregistrés et s’appliqueront aux prochains calculs.", "success");
    } catch (error) {
        showStatus(container, error.message || "L’enregistrement des paramètres a échoué.", "error");
    } finally {
        saveButton.removeAttribute("aria-busy");
        saveButton.textContent = "Enregistrer les paramètres";
        updateDirtyState(container);
    }
};

const initializeAlgorithmParameters = (container) => {
    if (container.dataset.adminAlgorithmParametersInitialized === "true") return;
    container.dataset.adminAlgorithmParametersInitialized = "true";
    container.adminAlgorithmParametersInputHandler = () => updateDirtyState(container);
    container.adminAlgorithmParametersSubmitHandler = event => saveAlgorithmParameters(container, event);
    inputs(container).forEach(input => input.addEventListener("input", container.adminAlgorithmParametersInputHandler));
    container.querySelector("[data-admin-algorithm-parameters-form]")
        .addEventListener("submit", container.adminAlgorithmParametersSubmitHandler);
    loadAlgorithmParameters(container);
};

const destroyAlgorithmParameters = (container) => {
    inputs(container).forEach(input => input.removeEventListener("input", container.adminAlgorithmParametersInputHandler));
    container.querySelector("[data-admin-algorithm-parameters-form]")
        ?.removeEventListener("submit", container.adminAlgorithmParametersSubmitHandler);
    delete container.dataset.adminAlgorithmParametersInitialized;
};

const initializeAllAlgorithmParameters = () => document
    .querySelectorAll("[data-admin-algorithm-parameters]")
    .forEach(initializeAlgorithmParameters);

document.addEventListener("DOMContentLoaded", initializeAllAlgorithmParameters);
document.addEventListener("turbo:load", initializeAllAlgorithmParameters);
document.addEventListener("turbo:before-cache", () => document
    .querySelectorAll("[data-admin-algorithm-parameters]")
    .forEach(destroyAlgorithmParameters));

initializeAllAlgorithmParameters();
