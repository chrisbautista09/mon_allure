const initializedToggles = new WeakSet();

function initializePasswordVisibility() {
    document.querySelectorAll("input[type=checkbox][data-password-toggle]").forEach((toggle) => {
        if (initializedToggles.has(toggle)) {
            return;
        }

        initializedToggles.add(toggle);
        const form = toggle.closest("form");
        const passwordFields = form?.querySelectorAll("input[data-password-field]") ?? [];

        const updateVisibility = () => {
            const inputType = toggle.checked ? "text" : "password";
            passwordFields.forEach((field) => {
                field.type = inputType;
            });
        };

        toggle.addEventListener("change", updateVisibility);
        updateVisibility();
    });
}

document.addEventListener("DOMContentLoaded", initializePasswordVisibility);
document.addEventListener("turbo:load", initializePasswordVisibility);
document.addEventListener("turbo:before-cache", () => {
    document.querySelectorAll("input[type=checkbox][data-password-toggle]").forEach((toggle) => {
        toggle.checked = false;
        toggle.dispatchEvent(new Event("change"));
    });
});
