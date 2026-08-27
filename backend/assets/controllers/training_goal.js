const goalType = document.querySelector("[data-training-goal-type]");

if (goalType) {
    const unit = document.querySelector("[data-training-goal-unit]");
    const duration = document.querySelector("[data-training-goal-race-duration]");
    const durationField = duration?.closest("[data-training-goal-field]");
    const value = document.querySelector("#training_goal_targetValue");
    const valueLabel = value ? document.querySelector(`label[for="${value.id}"]`) : null;
    const unitLabel = unit ? document.querySelector(`label[for="${unit.id}"]`) : null;

    const synchronizeFields = () => {
        const type = goalType.value;
        const isRace = type === "race";
        const isTime = type === "time";
        const allowedUnits = isTime ? ["min", "s"] : ["km", "m"];

        if (valueLabel) {
            valueLabel.textContent = isRace ? "Distance de l’épreuve" : (isTime ? "Durée cible" : "Distance cible");
        }
        if (unitLabel) {
            unitLabel.textContent = isRace ? "Unité de distance" : "Unité";
        }

        unit?.querySelectorAll("option[value]").forEach((option) => {
            if (option.value === "") {
                return;
            }

            const enabled = allowedUnits.includes(option.value);
            option.hidden = !enabled;
            option.disabled = !enabled;
        });

        if (unit?.value && !allowedUnits.includes(unit.value)) {
            unit.value = "";
        }

        if (durationField && duration) {
            durationField.hidden = !isRace;
            duration.required = isRace;
            if (!isRace) {
                duration.value = "";
            }
        }
    };

    goalType.addEventListener("change", synchronizeFields);
    synchronizeFields();
}
