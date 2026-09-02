const CLOSE_DELAY_MS = 500;
const initializedMenus = new WeakSet();

function initializeNavigationMenus() {
    const menus = document.querySelectorAll("details[data-auto-close-menu]");

    menus.forEach((menu) => {
        if (initializedMenus.has(menu)) {
            return;
        }

        initializedMenus.add(menu);
        let closeTimer = null;

        const cancelScheduledClose = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        };

        const scheduleClose = () => {
            cancelScheduledClose();
            closeTimer = window.setTimeout(() => {
                menu.open = false;
                closeTimer = null;
            }, CLOSE_DELAY_MS);
        };

        menu.addEventListener("mouseenter", cancelScheduledClose);
        menu.addEventListener("mouseleave", scheduleClose);
        menu.addEventListener("focusin", cancelScheduledClose);

        menu.addEventListener("toggle", () => {
            if (!menu.open) {
                cancelScheduledClose();
                return;
            }

            menus.forEach((otherMenu) => {
                if (otherMenu !== menu) {
                    otherMenu.open = false;
                }
            });
        });
    });
}

document.addEventListener("DOMContentLoaded", initializeNavigationMenus);
document.addEventListener("turbo:load", initializeNavigationMenus);
document.addEventListener("turbo:before-cache", () => {
    document.querySelectorAll("details[data-auto-close-menu]").forEach((menu) => {
        menu.open = false;
    });
});
