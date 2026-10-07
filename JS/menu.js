document.addEventListener("DOMContentLoaded", () => {
    // Header search (every page): Enter or the magnifier opens the shop filtered by the search
    const headerSearch = document.querySelector(".search-box input");
    if (headerSearch) {
        const goSearch = () => {
            const q = headerSearch.value.trim();
            if (q) window.location.href = `shop.html?q=${encodeURIComponent(q)}`;
        };
        headerSearch.addEventListener("keydown", (e) => {
            if (e.key === "Enter") goSearch();
        });
        headerSearch.closest(".search-box").querySelector("i").addEventListener("click", goSearch);
    }

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
