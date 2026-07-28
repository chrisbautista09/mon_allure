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

    trainingCalendar = new Calendar(calendarElement, {
        plugins: [dayGridPlugin, interactionPlugin],

        initialView: "dayGridMonth",
        initialDate: "2026-07-06",

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

        events: calendarElement.dataset.eventsUrl,

        eventClick(info) {
            if (!info.event.url) {
                return;
            }

            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
        },

        dateClick(info) {
            const weeklyUrl = calendarElement.dataset.weeklyUrl;

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
