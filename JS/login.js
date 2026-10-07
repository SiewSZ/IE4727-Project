document.addEventListener("DOMContentLoaded", () => {
    const authContainer = document.getElementById("authContainer");
    const registerBtn = document.getElementById("registerBtn");
    const loginBtn = document.getElementById("loginBtn");
    const closeBtn = document.getElementById("closeLoginBtn");
    const loginMessage = document.getElementById("loginMessage");
    const registerMessage = document.getElementById("registerMessage");

    if (!authContainer) return;

    const showSignUp = () => authContainer.classList.add("active");
    const showSignIn = () => authContainer.classList.remove("active");
    const goHome = () => { window.location.href = "index.html"; };

    registerBtn.addEventListener("click", showSignUp);
    loginBtn.addEventListener("click", showSignIn);
    closeBtn.addEventListener("click", goHome);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") goHome();
    });

    // The PHP handlers redirect back here with the result in the query string
    const params = new URLSearchParams(window.location.search);

    const loginErrors = {
        missing: "Please enter your username/email and password.",
        invalid: "Incorrect username/email or password."
    };

    const registerErrors = {
        missing: "Please fill in all fields.",
        username: "Username must be 3-50 letters, numbers or underscores.",
        email: "Please enter a valid email address.",
        password: "Password must be at least 8 characters.",
        taken: "That username or email is already registered."
    };

    if (params.get("mode") === "register") {
        showSignUp();
    }

    if (params.has("register_error")) {
        showSignUp();
        registerMessage.textContent = registerErrors[params.get("register_error")] || "Registration failed. Please try again.";
    } else if (params.has("login_error")) {
        loginMessage.textContent = loginErrors[params.get("login_error")] || "Login failed. Please try again.";
    } else if (params.get("register") === "success") {
        loginMessage.textContent = "Account created! Please sign in.";
        loginMessage.classList.add("success");
    }
});
