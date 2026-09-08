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
});
