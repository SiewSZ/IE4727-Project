document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("job-form");
    const nameField = document.getElementById("name");
    const emailField = document.getElementById("email");
    const startDateField = document.getElementById("start-date");
    const experienceField = document.getElementById("experience");
    const submitButton = form.querySelector('button[type="submit"]');

    const NAME_MIN_LENGTH = 2;
    const NAME_MAX_LENGTH = 50;
    const EXPERIENCE_MIN_LENGTH = 10;
    const START_DATE_MAX_MONTHS_AHEAD = 6;

    const namePattern = /^[A-Za-z ]+$/;
    const emailPattern = /^[^\s@]+@(\w+\.){1,3}\w{2,3}$/;

    // Runs a field's rules and returns the result instead of touching the DOM,
    // so the same logic can silently drive the submit button as well as display errors.
    function checkName() {
        const value = nameField.value.trim();
        if (value === "") {
            return { valid: false, message: "Please enter your name." };
        }
        if (!namePattern.test(value)) {
            return { valid: false, message: "Name can only contain letters and spaces." };
        }
        if (value.length < NAME_MIN_LENGTH || value.length > NAME_MAX_LENGTH) {
            return { valid: false, message: `Name must be between ${NAME_MIN_LENGTH} and ${NAME_MAX_LENGTH} characters.` };
        }
        return { valid: true, message: "" };
    }

    function checkEmail() {
        const value = emailField.value.trim();
        if (value === "") {
            return { valid: false, message: "Please enter your e-mail address." };
        }
        if (!emailPattern.test(value)) {
            return { valid: false, message: "Please enter a valid e-mail address." };
        }
        // A leading/trailing dot in the local part (e.g. ".john@x.com") passes the
        // shape regex above but is not a deliverable address, so check it separately.
        const localPart = value.split("@")[0];
        if (localPart.startsWith(".") || localPart.endsWith(".")) {
            return { valid: false, message: "E-mail cannot start or end with a period before the @." };
        }
        return { valid: true, message: "" };
    }

    function checkStartDate() {
        if (startDateField.value === "") {
            return { valid: true, message: "" };
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const chosenDate = new Date(startDateField.value);
        if (chosenDate <= today) {
            return { valid: false, message: "Start date cannot be today or in the past." };
        }
        // Caps how far out a start date can be requested, so the field can't be left
        // with a placeholder date years in the future.
        const maxDate = new Date(today);
        maxDate.setMonth(maxDate.getMonth() + START_DATE_MAX_MONTHS_AHEAD);
        if (chosenDate > maxDate) {
            return { valid: false, message: `Start date must be within ${START_DATE_MAX_MONTHS_AHEAD} months from today.` };
        }
        return { valid: true, message: "" };
    }

    function checkExperience() {
        const value = experienceField.value.trim();
        if (value === "") {
            return { valid: false, message: "Please describe your experience." };
        }
        if (value.length < EXPERIENCE_MIN_LENGTH) {
            return { valid: false, message: `Please provide at least ${EXPERIENCE_MIN_LENGTH} characters describing your experience.` };
        }
        return { valid: true, message: "" };
    }

    function applyValidation(field, result) {
        if (result.valid) {
            field.classList.remove("invalid");
            document.getElementById(field.id + "-error").textContent = "";
        } else {
            field.classList.add("invalid");
            document.getElementById(field.id + "-error").textContent = result.message;
        }
        return result.valid;
    }

    // Runs the checks without displaying anything, purely to decide whether the
    // submit button should be enabled -- so an untouched, empty form doesn't
    // flash error messages on page load.
    function updateSubmitButtonState() {
        const formIsValid =
            checkName().valid &&
            checkEmail().valid &&
            checkStartDate().valid &&
            checkExperience().valid;
        submitButton.disabled = !formIsValid;
    }

    // Delays validation until typing pauses, so longer free-text fields aren't
    // re-validated (and don't flash errors) on every single keystroke.
    function debounce(fn, delayMs) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delayMs);
        };
    }

    // Validate on blur rather than on every keystroke, so an error isn't shown
    // while the user is still in the middle of typing their first entry.
    nameField.addEventListener("blur", () => applyValidation(nameField, checkName()));
    emailField.addEventListener("blur", () => applyValidation(emailField, checkEmail()));
    startDateField.addEventListener("input", () => applyValidation(startDateField, checkStartDate()));
    experienceField.addEventListener(
        "input",
        debounce(() => applyValidation(experienceField, checkExperience()), 300)
    );

    // Once a field has an error shown, clear it as soon as the user edits it again,
    // instead of making them wait for the next blur/debounce to get feedback.
    [nameField, emailField, experienceField].forEach((field) => {
        field.addEventListener("input", () => {
            if (field.classList.contains("invalid")) {
                field.classList.remove("invalid");
                document.getElementById(field.id + "-error").textContent = "";
            }
            updateSubmitButtonState();
        });
    });
    startDateField.addEventListener("input", updateSubmitButtonState);

    updateSubmitButtonState();

    form.addEventListener("submit", function (event) {
        const isNameValid = applyValidation(nameField, checkName());
        const isEmailValid = applyValidation(emailField, checkEmail());
        const isStartDateValid = applyValidation(startDateField, checkStartDate());
        const isExperienceValid = applyValidation(experienceField, checkExperience());

        const isFormValid = isNameValid && isEmailValid && isStartDateValid && isExperienceValid;

        if (!isFormValid) {
            event.preventDefault();
            updateSubmitButtonState();
            const firstInvalidField = form.querySelector(".invalid");
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
});
