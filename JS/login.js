document.addEventListener("DOMContentLoaded", () => {
    const loginSection = document.getElementById("loginSection");
    const authContainer = document.getElementById("authContainer");
    const registerBtn = document.getElementById("registerBtn");
    const loginBtn = document.getElementById("loginBtn");
    const closeBtn = document.getElementById("closeLoginBtn");
    const openLoginBtn = document.getElementById("openLoginBtn");
    const footerSignInBtn = document.getElementById("footerSignInBtn");
    const footerSignUpBtn = document.getElementById("footerSignUpBtn");

    if (!loginSection) return;

    const closeLoginModal = () => {
        loginSection.classList.remove("active");
        document.body.classList.remove("login-active");
        authContainer.classList.remove("active");
    };

    const openLoginModal = () => {
        loginSection.classList.add("active");
        document.body.classList.add("login-active");
    };

    openLoginBtn.addEventListener("click", openLoginModal);

    if (footerSignInBtn) {
        footerSignInBtn.addEventListener("click", (e) => {
            e.preventDefault();
            authContainer.classList.remove("active");
            openLoginModal();
        });
    }

    if (footerSignUpBtn) {
        footerSignUpBtn.addEventListener("click", (e) => {
            e.preventDefault();
            authContainer.classList.add("active");
            openLoginModal();
        });
    }

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
