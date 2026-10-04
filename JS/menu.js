document.addEventListener("DOMContentLoaded", () => {
    const menuToggle = document.getElementById("menuToggle");
    const menuItem = menuToggle ? menuToggle.closest(".menu-item") : null;

    if (!menuToggle || !menuItem) return;

    const closeMenu = () => {
        menuItem.classList.remove("active");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        const isActive = menuItem.classList.toggle("active");
        menuToggle.setAttribute("aria-expanded", String(isActive));
    });

    document.addEventListener("click", (e) => {
        if (menuItem.classList.contains("active") && !menuItem.contains(e.target)) {
            closeMenu();
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && menuItem.classList.contains("active")) {
            closeMenu();
        }
    });
});
