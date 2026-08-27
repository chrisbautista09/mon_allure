const formatDate = (value) => new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
}).format(new Date(value));

const element = (tag, className, text = "") => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;

    return node;
};

const actionButton = (label, action, userId, className) => {
    const button = element("button", className, label);
    button.type = "button";
    button.dataset.adminUserAction = action;
    button.dataset.userId = userId;
    return button;
};

const userRow = (user, currentUserId) => {
    const row = document.createElement("tr");
    row.className = "transition hover:bg-white/5";
    row.dataset.userId = user.id;
    const pseudo = element("td", "whitespace-nowrap px-4 py-4 font-extrabold text-white", user.pseudo);
    const email = element("td", "px-4 py-4 text-white/65", user.email);
    const roleCell = element("td", "px-4 py-4");
    const isAdmin = user.roles.includes("ROLE_ADMIN");
    roleCell.append(element(
        "span",
        `inline-flex rounded-full px-3 py-1 text-xs font-extrabold ${isAdmin ? "bg-violet-300/15 text-violet-200" : "bg-cyan-300/10 text-cyan-200"}`,
        isAdmin ? "Administrateur" : "Utilisateur",
    ));
    const statusCell = element("td", "px-4 py-4");
    statusCell.append(element(
        "span",
        `inline-flex rounded-full px-3 py-1 text-xs font-extrabold ${user.isActive ? "bg-emerald-300/15 text-emerald-200" : "bg-rose-300/15 text-rose-200"}`,
        user.isActive ? "Actif" : "Désactivé",
    ));
    const createdAt = element("td", "whitespace-nowrap px-4 py-4 text-white/55", formatDate(user.createdAt));
    const actions = element("td", "px-4 py-4");
    const actionGroup = element("div", "flex justify-end gap-2");
    const toggleButton = actionButton(
        user.isActive ? "Désactiver" : "Activer",
        user.isActive ? "deactivate" : "activate",
        user.id,
        "rounded-lg border border-amber-300/30 px-3 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-300/10 disabled:cursor-not-allowed disabled:opacity-35",
    );
    if (String(user.id) === currentUserId) {
        toggleButton.disabled = true;
        toggleButton.title = "Vous ne pouvez pas désactiver votre propre compte";
    }
    const deleteButton = actionButton(
        "Supprimer",
        "delete",
        user.id,
        "rounded-lg border border-rose-300/30 px-3 py-2 text-xs font-bold text-rose-200 transition hover:bg-rose-300/10 disabled:cursor-not-allowed disabled:opacity-35",
    );
    if (String(user.id) === currentUserId) {
        deleteButton.disabled = true;
        deleteButton.title = "Vous ne pouvez pas supprimer votre propre compte";
    }
    actionGroup.append(toggleButton, deleteButton);
    actions.append(actionGroup);
    row.append(pseudo, email, roleCell, statusCell, createdAt, actions);

    return row;
};

const showFeedback = (container, message, isError = false) => {
    const feedback = container.querySelector("[data-admin-users-feedback]");
    feedback.textContent = message;
    feedback.className = `mt-6 rounded-xl border px-4 py-3 text-sm font-bold ${isError
        ? "border-rose-300/30 bg-rose-300/10 text-rose-100"
        : "border-emerald-300/30 bg-emerald-300/10 text-emerald-100"}`;
    feedback.hidden = false;
};

const toggleUserStatus = async (container, button) => {
    const activate = button.dataset.adminUserAction === "activate";
    const verb = activate ? "activer" : "désactiver";
    if (!window.confirm(`Confirmer : ${verb} ce compte utilisateur ?`)) return;

    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    const originalLabel = button.textContent;
    button.textContent = activate ? "Activation…" : "Désactivation…";

    try {
        const response = await fetch(`${container.dataset.adminUsersUrl}/${button.dataset.userId}`, {
            method: "PATCH",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": container.dataset.adminUsersCsrf,
            },
            body: JSON.stringify({ isActive: activate }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || "La modification du compte a échoué.");

        showFeedback(container, activate ? "Le compte a bien été activé." : "Le compte a bien été désactivé.");
        await loadUsers(container, container.adminUsersPage);
    } catch (error) {
        showFeedback(container, error.message || "La modification du compte a échoué.", true);
        button.disabled = false;
        button.textContent = originalLabel;
    } finally {
        button.removeAttribute("aria-busy");
    }
};

const deleteUser = async (container, button) => {
    if (!window.confirm("Supprimer définitivement ce compte et toutes ses données associées ? Cette action est irréversible.")) return;

    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    const originalLabel = button.textContent;
    button.textContent = "Suppression…";

    try {
        const response = await fetch(`${container.dataset.adminUsersUrl}/${button.dataset.userId}`, {
            method: "DELETE",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": container.dataset.adminUsersCsrf,
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || "La suppression du compte a échoué.");

        showFeedback(container, "Le compte et ses données associées ont bien été supprimés.");
        const remainingRows = container.querySelectorAll("[data-admin-users-body] tr").length - 1;
        const targetPage = remainingRows === 0 && container.adminUsersPage > 1
            ? container.adminUsersPage - 1
            : container.adminUsersPage;
        await loadUsers(container, targetPage);
    } catch (error) {
        showFeedback(container, error.message || "La suppression du compte a échoué.", true);
        button.disabled = false;
        button.textContent = originalLabel;
    } finally {
        button.removeAttribute("aria-busy");
    }
};

const loadUsers = async (container, page) => {
    const status = container.querySelector("[data-admin-users-status]");
    const table = container.querySelector("[data-admin-users-table]");
    const empty = container.querySelector("[data-admin-users-empty]");
    const pagination = container.querySelector("[data-admin-users-pagination]");
    const url = new URL(container.dataset.adminUsersUrl, window.location.origin);
    url.searchParams.set("page", page);
    url.searchParams.set("perPage", "20");
    const search = container.querySelector("[data-admin-users-search]").value.trim();
    const statusFilter = container.querySelector("[data-admin-users-status-filter]").value;
    const roleFilter = container.querySelector("[data-admin-users-role-filter]").value;
    if (search) url.searchParams.set("search", search);
    if (statusFilter !== "all") url.searchParams.set("status", statusFilter);
    if (roleFilter !== "all") url.searchParams.set("role", roleFilter);
    container.adminUsersAbortController?.abort();
    container.adminUsersAbortController = new AbortController();
    table.hidden = true;
    empty.hidden = true;
    pagination.hidden = true;
    status.hidden = false;
    status.textContent = "Chargement des utilisateurs…";
    status.classList.remove("text-amber-200");

    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            signal: container.adminUsersAbortController.signal,
        });

        if (!response.ok) throw new Error("Admin users request failed");

        const payload = await response.json();
        const users = Array.isArray(payload.items) ? payload.items : [];
        status.hidden = true;
        container.adminUsersPage = payload.pagination.page;
        container.adminUsersTotalPages = payload.pagination.totalPages;
        container.querySelector("[data-admin-user-total]").textContent = payload.pagination.total;
        container.querySelector("[data-admin-user-total-plural]").hidden = payload.pagination.total === 1;

        if (users.length === 0) {
            empty.hidden = false;
            return;
        }

        container.querySelector("[data-admin-users-body]").replaceChildren(
            ...users.map(user => userRow(user, container.dataset.adminCurrentUserId)),
        );
        container.querySelector("[data-admin-users-page]").textContent = `Page ${payload.pagination.page} sur ${payload.pagination.totalPages}`;
        container.querySelector("[data-admin-users-previous]").disabled = payload.pagination.page <= 1;
        container.querySelector("[data-admin-users-next]").disabled = payload.pagination.page >= payload.pagination.totalPages;
        table.hidden = false;
        pagination.hidden = false;
    } catch (error) {
        if (error.name === "AbortError") return;

        status.textContent = "Impossible de charger les utilisateurs pour le moment.";
        status.classList.add("text-amber-200");
    }
};

const initializeAdminUsers = (container) => {
    if (container.dataset.adminUsersInitialized === "true") return;
    container.dataset.adminUsersInitialized = "true";
    container.adminUsersPreviousHandler = () => loadUsers(container, Math.max(1, container.adminUsersPage - 1));
    container.adminUsersNextHandler = () => loadUsers(container, container.adminUsersPage + 1);
    container.adminUsersFilterHandler = (event) => {
        event.preventDefault();
        loadUsers(container, 1);
    };
    container.adminUsersFilterChangeHandler = () => loadUsers(container, 1);
    container.adminUsersResetHandler = (event) => {
        event.preventDefault();
        container.querySelector("[data-admin-users-search]").value = "";
        container.querySelector("[data-admin-users-status-filter]").value = "all";
        container.querySelector("[data-admin-users-role-filter]").value = "all";
        loadUsers(container, 1);
    };
    container.adminUsersActionHandler = (event) => {
        const button = event.target.closest("[data-admin-user-action]");
        if (!button || button.disabled) return;
        if (["activate", "deactivate"].includes(button.dataset.adminUserAction)) {
            toggleUserStatus(container, button);
        } else if (button.dataset.adminUserAction === "delete") {
            deleteUser(container, button);
        }
    };
    container.querySelector("[data-admin-users-previous]").addEventListener("click", container.adminUsersPreviousHandler);
    container.querySelector("[data-admin-users-next]").addEventListener("click", container.adminUsersNextHandler);
    container.querySelector("[data-admin-users-body]").addEventListener("click", container.adminUsersActionHandler);
    container.querySelector("[data-admin-users-filters]").addEventListener("submit", container.adminUsersFilterHandler);
    container.querySelector("[data-admin-users-status-filter]").addEventListener("change", container.adminUsersFilterChangeHandler);
    container.querySelector("[data-admin-users-role-filter]").addEventListener("change", container.adminUsersFilterChangeHandler);
    container.querySelector("[data-admin-users-reset]").addEventListener("click", container.adminUsersResetHandler);
    loadUsers(container, 1);
};

const destroyAdminUsers = (container) => {
    container.adminUsersAbortController?.abort();
    container.querySelector("[data-admin-users-previous]")?.removeEventListener("click", container.adminUsersPreviousHandler);
    container.querySelector("[data-admin-users-next]")?.removeEventListener("click", container.adminUsersNextHandler);
    container.querySelector("[data-admin-users-body]")?.removeEventListener("click", container.adminUsersActionHandler);
    container.querySelector("[data-admin-users-filters]")?.removeEventListener("submit", container.adminUsersFilterHandler);
    container.querySelector("[data-admin-users-status-filter]")?.removeEventListener("change", container.adminUsersFilterChangeHandler);
    container.querySelector("[data-admin-users-role-filter]")?.removeEventListener("change", container.adminUsersFilterChangeHandler);
    container.querySelector("[data-admin-users-reset]")?.removeEventListener("click", container.adminUsersResetHandler);
    delete container.dataset.adminUsersInitialized;
};

const initializeAllAdminUsers = () => document.querySelectorAll("[data-admin-users]").forEach(initializeAdminUsers);

document.addEventListener("DOMContentLoaded", initializeAllAdminUsers);
document.addEventListener("turbo:load", initializeAllAdminUsers);
document.addEventListener("turbo:before-cache", () => document.querySelectorAll("[data-admin-users]").forEach(destroyAdminUsers));

initializeAllAdminUsers();
