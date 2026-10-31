document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("contactForm");
    if (!form) return;

    const nameInput = document.getElementById("contactName");
    const emailInput = document.getElementById("contactEmail");
    const subjectSelect = document.getElementById("contactSubject");
    const messageInput = document.getElementById("contactMessage");
    const messageCount = document.getElementById("messageCount");
    const status = document.getElementById("contactStatus");

    // Checks HTML5 can't do on its own. Each returns an error message, or "" when the value is fine.
    const checks = {
        // Letters, spaces, apostrophes, dots and hyphens, e.g. "Tan Ah Kow" or "O'Brien"
        name: () => {
            const name = nameInput.value.trim();
            if (name === "") return "Please enter your name.";
            if (!/^[A-Za-z][A-Za-z .'-]{1,99}$/.test(name)) return "Name can only use letters, spaces, apostrophes, dots and hyphens.";
            return "";
        },
        // type="email" accepts "name@host"; a shop reply needs a full domain like "name@host.com"
        email: () => {
            const email = emailInput.value.trim();
            if (email === "") return "Please enter your email address.";
            if (!/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(email)) return "Please enter a valid email address, e.g. name@example.com.";
            return "";
        },
        // minlength counts spaces, so check the message has real text in it
        message: () => {
            const message = messageInput.value.trim();
            if (message === "") return "Please enter your message.";
            if (message.length < 10) return "Please write at least 10 characters so we can help.";
            return "";
        }
    };

    const fields = { name: nameInput, email: emailInput, message: messageInput };

    const showError = (key) => {
        const error = checks[key]();
        document.getElementById(`${key}Error`).textContent = error;
        fields[key].classList.toggle("invalid", error !== "");
        return error === "";
    };

    // Check a field when the visitor leaves it, and re-check it while they fix it
    Object.entries(fields).forEach(([key, input]) => {
        input.addEventListener("blur", () => showError(key));
        input.addEventListener("input", () => {
            if (input.classList.contains("invalid")) showError(key);
        });
    });

    messageInput.addEventListener("input", () => {
        messageCount.textContent = messageInput.value.length;
    });

    form.addEventListener("submit", (e) => {
        // Run every check (not just until the first failure) so all errors show at once
        const results = Object.keys(fields).map(showError);
        if (results.includes(false)) {
            e.preventDefault();
            form.querySelector(".invalid").focus();
        }
    });

    // Menu links like contact.html?subject=trade-in pick the subject; PHP/contact/send.php
    // redirects back here with ?sent=1 or ?error=<code>
    const params = new URLSearchParams(window.location.search);

    if ([...subjectSelect.options].some((option) => option.value === params.get("subject"))) {
        subjectSelect.value = params.get("subject");
    }

    const serverErrors = {
        missing: "Please fill in your name, email and message.",
        name: "Name can only use letters, spaces, apostrophes, dots and hyphens.",
        email: "Please enter a valid email address.",
        message: "Your message must be between 10 and 2000 characters."
    };

    if (params.get("sent") === "1") {
        status.textContent = "Thank you! Your message has been sent. We will reply within one working day.";
        status.classList.add("success");
    } else if (params.has("error")) {
        status.textContent = serverErrors[params.get("error")] || "Your message could not be sent. Please try again.";
    }
});
