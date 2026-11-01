document.addEventListener("DOMContentLoaded", () => {
    // Messages left by a form handler (flashMessage() in PHP/page.php): close button,
    // and success messages close on their own after a few seconds
    document.querySelectorAll(".flash").forEach((flash) => {
        const close = () => flash.remove();
        flash.querySelector(".flash-close").addEventListener("click", close);
        if (!flash.classList.contains("error")) setTimeout(close, 6000);
    });

    // Highlight the nav link for the page being viewed (index.php when the URL ends in "/")
    const currentPage = window.location.pathname.split("/").pop() || "index.php";
    document.querySelectorAll(".nav-links > li > a").forEach((link) => {
        if (link.getAttribute("href") === currentPage) link.setAttribute("aria-current", "page");
    });

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

    // Links like hotdeals.php#clearance may only scroll the current page, so close the menu ourselves
    menuItem.querySelectorAll(".mega-menu a").forEach((link) => {
        link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && menuItem.classList.contains("active")) {
            closeMenu();
        }
    });
});
