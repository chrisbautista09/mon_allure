import "fullcalendar";
import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import interactionPlugin from "@fullcalendar/interaction";

let trainingCalendar = null;

function initializeTrainingCalendar() {
    const calendarElement = document.getElementById("training-calendar");

    console.log("Recherche du calendrier :", calendarElement);

    if (!calendarElement) {
        return;
    }

    if (calendarElement.dataset.calendarInitialized === "true") {
        return;
    }

    calendarElement.dataset.calendarInitialized = "true";

    const trainingStart = calendarElement.dataset.trainingStart;

    const trainingEnd = calendarElement.dataset.trainingEnd;

    const weeklyUrl = calendarElement.dataset.weeklyUrl;

    trainingCalendar = new Calendar(calendarElement, {
        plugins: [dayGridPlugin, interactionPlugin],

        initialView: "dayGridMonth",
        initialDate: trainingStart || "2026-07-06",

        locale: "fr",
        firstDay: 1,

        height: "auto",
        contentHeight: "auto",
        expandRows: true,

        fixedWeekCount: false,
        showNonCurrentDates: true,
        dayMaxEvents: 2,

        headerToolbar: {
            left: "prev,next",
            center: "title",
            right: "today",
        },

        buttonText: {
            today: "Aujourd’hui",
        },

        /*
         * Ajoute une classe CSS aux jours appartenant
         * à la période du plan.
         */
        dayCellClassNames(info) {
            const cellDate = formatLocalDate(info.date);

            const classes = [];

            if (
                trainingStart &&
                trainingEnd &&
                cellDate >= trainingStart &&
                cellDate <= trainingEnd
            ) {
                classes.push("training-period-day");
            } else {
                classes.push("outside-training-period");
            }

            return classes;
        },

        dayCellDidMount(info) {
            /*
             * Le repère est placé dans la case du dimanche,
             * donc tout à droite de chaque ligne.
             */
            if (info.date.getDay() !== 0) {
                return;
            }

            const dayFrame = info.el.querySelector(".fc-daygrid-day-frame");

            if (!dayFrame) {
                return;
            }

            const weekStartDate = getMondayDate(info.date);
            const weekEndDate = new Date(info.date);

            const formattedWeekStart = formatLocalDate(weekStartDate);

            const formattedWeekEnd = formatLocalDate(weekEndDate);

            /*
             * Ne crée aucun repère si la semaine entière
             * est située hors de la période du plan.
             */
            if (
                !trainingStart ||
                !trainingEnd ||
                formattedWeekEnd < trainingStart ||
                formattedWeekStart > trainingEnd
            ) {
                return;
            }

            const weekNumber = getTrainingWeekNumber(
                weekStartDate,
                trainingStart,
            );

            if (weekNumber < 1) {
                return;
            }

            const currentWeek = Number(
                calendarElement.dataset.currentWeek || 1,
            );

            const weekStatus = getWeekStatus(weekNumber, currentWeek);

            const weekButton = document.createElement("button");

            weekButton.type = "button";

            weekButton.className = [
                "calendar-week-marker",
                `calendar-week-marker--${weekStatus}`,
            ].join(" ");

            /*
             * Symbole d’état.
             */
            const statusIcon = document.createElement("span");

            statusIcon.className = "calendar-week-marker__icon";

            statusIcon.setAttribute("aria-hidden", "true");

            statusIcon.textContent = getWeekStatusIcon(weekStatus);

            /*
             * Numéro de la semaine du plan.
             */
            const weekLabel = document.createElement("span");

            weekLabel.className = "calendar-week-marker__label";

            weekLabel.textContent = `S${weekNumber}`;

            weekButton.append(statusIcon, weekLabel);

            const accessibleStatus = getAccessibleWeekStatus(weekStatus);

            weekButton.title = `Semaine ${weekNumber} — ${accessibleStatus}`;

            weekButton.setAttribute(
                "aria-label",
                `Afficher la semaine ${weekNumber} du plan, ${accessibleStatus}`,
            );

            weekButton.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (!weeklyUrl) {
                    return;
                }

                const url = new URL(weeklyUrl, window.location.origin);

                url.searchParams.set("date", formattedWeekStart);

                url.searchParams.set("week", String(weekNumber));

                window.location.href = url.toString();
            });

            dayFrame.appendChild(weekButton);
        },

        events: calendarElement.dataset.eventsUrl,

        eventClick(info) {
            if (!info.event.url) {
                return;
            }

            info.jsEvent.preventDefault();

            window.location.href = info.event.url;
        },

        dateClick(info) {
            if (!weeklyUrl) {
                return;
            }

            const url = new URL(weeklyUrl, window.location.origin);

            url.searchParams.set("date", info.dateStr);

            window.location.href = url.toString();
        },
    });

    trainingCalendar.render();

    console.log("FullCalendar affiché");
}

function formatLocalDate(date) {
    const year = date.getFullYear();

    const month = String(date.getMonth() + 1).padStart(2, "0");

    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}
function getMondayDate(date) {
    const monday = new Date(date);

    const currentDay = monday.getDay();

    const difference = currentDay === 0 ? -6 : 1 - currentDay;

    monday.setDate(monday.getDate() + difference);

    monday.setHours(0, 0, 0, 0);

    return monday;
}

function parseLocalDate(dateString) {
    const [year, month, day] = dateString.split("-").map(Number);

    return new Date(year, month - 1, day);
}

function getTrainingWeekNumber(weekStartDate, trainingStart) {
    const planStartDate = parseLocalDate(trainingStart);

    const planFirstMonday = getMondayDate(planStartDate);

    const millisecondsPerWeek = 7 * 24 * 60 * 60 * 1000;

    const difference = weekStartDate.getTime() - planFirstMonday.getTime();

    return Math.floor(difference / millisecondsPerWeek) + 1;
}

function getWeekStatus(weekNumber, currentWeek) {
    if (weekNumber < currentWeek) {
        return "completed";
    }

    if (weekNumber === currentWeek) {
        return "current";
    }

    return "upcoming";
}

function getWeekStatusIcon(status) {
    const icons = {
        completed: "✓",
        current: "▶",
        upcoming: "○",
    };

    return icons[status] || "○";
}

function getAccessibleWeekStatus(status) {
    const labels = {
        completed: "semaine terminée",
        current: "semaine en cours",
        upcoming: "semaine à venir",
    };

    return labels[status] || "semaine du plan";
}

function destroyTrainingCalendar() {
    if (trainingCalendar !== null) {
        trainingCalendar.destroy();
        trainingCalendar = null;
    }

    const calendarElement = document.getElementById("training-calendar");

    if (calendarElement) {
        delete calendarElement.dataset.calendarInitialized;
    }
}

document.addEventListener("DOMContentLoaded", initializeTrainingCalendar);

document.addEventListener("turbo:load", initializeTrainingCalendar);

document.addEventListener("turbo:before-cache", destroyTrainingCalendar);

if (document.readyState !== "loading") {
    initializeTrainingCalendar();
}
