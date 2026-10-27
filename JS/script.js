document.addEventListener("DOMContentLoaded", () => {
    const loginSection = document.getElementById("loginSection");
    const authContainer = document.getElementById("authContainer");
    const registerBtn = document.getElementById("registerBtn");
    const loginBtn = document.getElementById("loginBtn");
    const closeBtn = document.getElementById("closeLoginBtn");
    const openLoginBtn = document.getElementById("openLoginBtn");

    const closeLoginModal = () => {
        loginSection.classList.remove("active");
        document.body.classList.remove("login-active");
        authContainer.classList.remove("active");
    };

    openLoginBtn.addEventListener("click", () => {
        loginSection.classList.add("active");
        document.body.classList.add("login-active");
    });

    registerBtn.addEventListener("click", () => {
        authContainer.classList.add("active");
    });

    loginBtn.addEventListener("click", () => {
        authContainer.classList.remove("active");
    });

    closeBtn.addEventListener("click", closeLoginModal);

    loginSection.addEventListener("click", (e) => {
        if (e.target === loginSection) {
            closeLoginModal();
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && loginSection.classList.contains("active")) {
            closeLoginModal();
        }
    });
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    showRegisterResult();

    function showRegisterResult() {
        const params = new URLSearchParams(window.location.search);
        const errorMessages = {
            missing: "Please fill in all fields.",
            username: "Username must be 3-50 letters, numbers or underscores.",
            email: "Please enter a valid email address.",
            password: "Password must be at least 8 characters.",
            mismatch: "Passwords do not match.",
            taken: "That username or email is already registered."
        };

        if (params.has("register_error")) {
            const message = errorMessages[params.get("register_error")] || "Registration failed. Please try again.";
            openLoginBtn.click();
            authContainer.classList.add("active");      // show the sign-up panel
            setMessage("registerMessage", message, "error");
        } else if (params.get("registered") === "1") {
            openLoginBtn.click();                       // sign-in panel is shown by default
            setMessage("loginMessage", "Account created! You can now log in.", "success");
        } else {
            return;
        }

        // Remove ?register_error=... from the address bar so a refresh doesn't show it again
        history.replaceState(null, "", window.location.pathname);
    }

    function setMessage(id, text, type) {
        const el = document.getElementById(id);
        el.textContent = text;
        el.className = "form-message " + type;
    }
});
